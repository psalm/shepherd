<?php

use Psalm\Shepherd\DatabaseProvider;
use Psalm\Shepherd\DatabaseUnavailableException;

require __DIR__ . '/../vendor/autoload.php';

// One-off schema maintenance for a server without shell or SQL access: adds the github_master_commits indexes from
// init.sql (#39) and reports the production type of psalm_reports.level (#38). Idempotent and takes no input.
// Remove once it has run.

const MAINTENANCE_LOCK = 'shepherd_schema_maintenance';

// Must match init.sql. The home page looks up repo_recent by name, so keep that name. Keep repo_recent first too:
// both indexes cover `WHERE owner_name, repo_name ORDER BY id`, MySQL and MariaDB take the earlier one on a cost
// tie, and only repo_recent serves that order without a filesort.
const INDEXES = [
    'repo_recent' => 'owner_name, repo_name, id',
    'repo_history' => 'owner_name, repo_name, created_on, id',
];

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

// Building the index must not stop halfway because the browser gave up waiting.
ignore_user_abort(true);
set_time_limit(0);

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

/** @return array<string, string> index name => comma-separated columns, for the INDEXES that exist */
$existingIndexes = static function () use ($pdo) : array {
    /** @var array<string, string> */
    return $pdo->query(
        "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'github_master_commits'
            AND INDEX_NAME IN ('" . implode("', '", array_keys(INDEXES)) . "')
            GROUP BY INDEX_NAME"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
};

/**
 * @param array<string, string> $existing
 * @return array<string, array{status: string, columns: ?string}>
 */
$indexReport = static function (array $existing, string $missing_status) : array {
    $report = [];

    foreach (array_keys(INDEXES) as $name) {
        $report[$name] = [
            'status' => isset($existing[$name]) ? 'already exists' : $missing_status,
            'columns' => $existing[$name] ?? null,
        ];
    }

    return $report;
};

$level_column = $pdo->query(
    "SELECT COLUMN_TYPE AS type, IS_NULLABLE AS nullable, COLUMN_DEFAULT AS `default`
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'psalm_reports'
        AND COLUMN_NAME = 'level'"
)->fetch(PDO::FETCH_ASSOC);

$report = [
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'psalm_reports.level' => $level_column ?: null,
];

// A second request must not queue another ALTER: a waiting ALTER holds up every query on the table behind it.
if ((int) $pdo->query("SELECT GET_LOCK('" . MAINTENANCE_LOCK . "', 0)")->fetchColumn() !== 1) {
    $respond(409, $report + ['indexes' => 'another request is applying them, reload in a minute']);

    return;
}

try {
    $before = $existingIndexes();
    $missing = array_diff_key(INDEXES, $before);

    if (!$missing) {
        $respond(200, $report + ['indexes' => $indexReport($before, 'already exists')]);

        return;
    }

    $add_clauses = [];

    foreach ($missing as $name => $columns) {
        $add_clauses[] = 'ADD INDEX ' . $name . ' (' . $columns . ')';
    }

    // Give up instead of blocking hook inserts if a long transaction holds the table.
    $pdo->exec('SET SESSION lock_wait_timeout = 5');

    $started = microtime(true);

    try {
        // Production (MariaDB 10.5) keeps created_on in the pre-10.1 temporal format, so it refuses INPLACE and the
        // table has to be rebuilt (about 200k rows, 34 MB). LOCK=SHARED keeps reads working; inserts wait for the copy.
        $pdo->exec(
            'ALTER TABLE github_master_commits ' . implode(', ', $add_clauses) . ', ALGORITHM=COPY, LOCK=SHARED'
        );
    } catch (PDOException $e) {
        error_log('Schema maintenance failed - ' . $e->getMessage());
        $respond(500, $report + ['indexes' => $indexReport($before, 'failed'), 'error' => $e->getMessage()]);

        return;
    }

    $seconds = round(microtime(true) - $started, 2);
    $after = $existingIndexes();
    $indexes = $indexReport($after, 'missing after ALTER');

    foreach (array_keys($missing) as $name) {
        if (isset($after[$name])) {
            $indexes[$name]['status'] = 'created';
        }
    }

    $respond(200, $report + ['indexes' => $indexes, 'seconds' => $seconds]);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('" . MAINTENANCE_LOCK . "')");
}
