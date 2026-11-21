<?php

use Psalm\Shepherd\DatabaseProvider;
use Psalm\Shepherd\DatabaseUnavailableException;

require __DIR__ . '/../vendor/autoload.php';

// One-off schema maintenance for a server without shell or SQL access: adds the repo_history index from init.sql (#39)
// and reports the production type of psalm_reports.level (#38). Idempotent and takes no input. Remove once it has run.

const MAINTENANCE_LOCK = 'shepherd_schema_maintenance';

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

$indexColumns = static function () use ($pdo) : ?string {
    $columns = $pdo->query(
        "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'github_master_commits'
            AND INDEX_NAME = 'repo_history'"
    )->fetchColumn();

    return is_string($columns) ? $columns : null;
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
    $respond(409, $report + ['repo_history' => 'another request is applying it, reload in a minute']);

    return;
}

try {
    $existing = $indexColumns();

    if ($existing !== null) {
        $respond(200, $report + ['repo_history' => 'already exists', 'repo_history_columns' => $existing]);

        return;
    }

    // Give up instead of blocking hook inserts if a long transaction holds the table.
    $pdo->exec('SET SESSION lock_wait_timeout = 5');

    $started = microtime(true);

    try {
        // INPLACE with LOCK=NONE keeps the table writable while the index builds, or fails if this MySQL can't do that.
        $pdo->exec(
            'ALTER TABLE github_master_commits
                ADD INDEX repo_history (owner_name, repo_name, created_on, id),
                ALGORITHM=INPLACE, LOCK=NONE'
        );
    } catch (PDOException $e) {
        error_log('repo_history index failed - ' . $e->getMessage());
        $respond(500, $report + ['repo_history' => 'failed', 'error' => $e->getMessage()]);

        return;
    }

    $respond(200, $report + [
        'repo_history' => 'created',
        'repo_history_columns' => $indexColumns(),
        'seconds' => round(microtime(true) - $started, 2),
    ]);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('" . MAINTENANCE_LOCK . "')");
}
