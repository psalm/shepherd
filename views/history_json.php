<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\DatabaseUnavailableException;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

/** @param int<400, 599> $status */
$respondError = static function (int $status, string $message) : void {
    http_response_code($status);
    header('Cache-Control: no-cache');
    echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);
};

$repository = Api::extractRepository($_SERVER['QUERY_STRING'] ?? '');

if ($repository === null) {
    $respondError(400, 'Repository format not recognised');

    return;
}

try {
    $payload = Api::getHistoryPayload($repository);
} catch (DatabaseUnavailableException $e) {
    $respondError(503, 'Database unavailable');

    return;
} catch (PDOException $e) {
    error_log('history.json query failed - ' . $e->getMessage());
    $respondError(500, 'Internal error');

    return;
}

if (!$payload['t']) {
    $respondError(404, 'No type coverage reports recorded');

    return;
}

header('Cache-Control: public, max-age=300');

// Psalm treats every PDO fetch (here the commit shas) as tainted input. This is a JSON response with a JSON content
// type, never parsed as HTML, so the html/quotes sinks do not apply.
/**
 * @psalm-taint-escape html
 * @psalm-taint-escape has_quotes
 */
$json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

echo $json;
