<?php

use Psalm\Shepherd\Api;

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json');

$repository = Api::extractRepository($_SERVER['QUERY_STRING'] ?? '');

if ($repository === null) {
    http_response_code(400);
    header('Cache-Control: no-cache');
    echo json_encode(['error' => 'Repository format not recognised'], JSON_THROW_ON_ERROR);

    return;
}

$history = Api::getHistory($repository);

if (!$history) {
    http_response_code(404);
    header('Cache-Control: no-cache');
    echo json_encode(['error' => 'No type coverage reports recorded'], JSON_THROW_ON_ERROR);

    return;
}

header('Cache-Control: public, max-age=300');

// Compact columnar payload, ascending by time: t = unix seconds, c = coverage % (unrounded, rounded only for display),
// e = expressions, s = commit sha.
$data = ['repo' => $repository, 't' => [], 'c' => [], 'e' => [], 's' => []];

foreach (array_reverse($history) as $row) {
    $data['t'][] = $row['created_at'];
    $data['c'][] = $row['coverage'];
    $data['e'][] = $row['total'];
    $data['s'][] = $row['commit'];
}

// Served as application/json, never parsed as HTML, so DB values are not an XSS vector here.
/**
 * @psalm-taint-escape html
 * @psalm-taint-escape has_quotes
 */
$json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

echo $json;
