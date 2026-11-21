<?php

use Psalm\Shepherd\DatabaseProvider;
use Psalm\Shepherd\DatabaseUnavailableException;

require __DIR__ . '/../vendor/autoload.php';

// One-off, read-only schema report for a server without shell or SQL access. Production refused the online index build
// (#39), so this reports what a table rebuild would involve before anything changes. Takes no input and writes nothing.
// Remove once the indexes are settled.

// Must match init.sql.
const INDEXES = [
    'repo_recent' => 'owner_name, repo_name, id',
    'repo_history' => 'owner_name, repo_name, created_on, id',
];

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

/** @param array<string, mixed> $body */
$respond = static function (int $status, array $body) : void {
    http_response_code($status);

    // Psalm treats every PDO fetch as tainted input. This is a JSON response with a JSON content type, never parsed
    // as HTML, so the html/quotes sinks do not apply.
    /**
     * @psalm-taint-escape html
     * @psalm-taint-escape has_quotes
     */
    $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    echo $json;
};

try {
    $pdo = DatabaseProvider::getConnection();
} catch (DatabaseUnavailableException $e) {
    $respond(503, ['error' => 'Database unavailable']);

    return;
}

// MariaDB marks columns still in the pre-10.1 temporal format with "/* mariadb-5.3 */" when this is on. Such columns
// force any ALTER to rebuild the table. MySQL doesn't know the variable.
try {
    $pdo->exec('SET SESSION show_old_temporals = ON');
} catch (PDOException) {
}

/** @var array<string, string> $columns column name => type */
$columns = $pdo->query(
    "SELECT COLUMN_NAME, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'github_master_commits'
        ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$table = $pdo->query(
    "SELECT ENGINE AS engine,
            ROW_FORMAT AS row_format,
            ROUND(DATA_LENGTH / 1048576, 1) AS data_mb,
            ROUND(INDEX_LENGTH / 1048576, 1) AS index_mb,
            CREATE_TIME AS created
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'github_master_commits'"
)->fetch(PDO::FETCH_ASSOC);

/** @var array<string, string> $existing index name => comma-separated columns */
$existing = $pdo->query(
    "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'github_master_commits'
        GROUP BY INDEX_NAME"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$indexes = [];

foreach (array_keys(INDEXES) as $name) {
    $indexes[$name] = $existing[$name] ?? 'missing';
}

$level_column = $pdo->query(
    "SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS `default`
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'psalm_reports'
        AND COLUMN_NAME = 'level'"
)->fetch(PDO::FETCH_ASSOC);

$respond(200, [
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'github_master_commits' => [
        'rows' => (int) $pdo->query('SELECT COUNT(*) FROM github_master_commits')->fetchColumn(),
        'table' => $table ?: null,
        'columns' => $columns,
        'all_indexes' => $existing,
        'wanted_indexes' => $indexes,
    ],
    'psalm_reports.level' => $level_column ?: null,
]);
