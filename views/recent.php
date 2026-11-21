<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;
use Psalm\Shepherd\DatabaseUnavailableException;
use Psalm\Shepherd\Format;

require __DIR__ . '/../vendor/autoload.php';

/**
 * The <tbody> of the "Recently active" table on the home page, polled by assets/js/home.js.
 * Same data (60 s file cache) and same renderer as the page itself. The ETag is a hash of the body, so an
 * unchanged table costs a 304 without a body.
 */

$respondUnavailable = static function (int $status) : void {
    http_response_code($status);
    header('Cache-Control: no-store');
};

try {
    $activity = Api::getRecentActivity();
} catch (DatabaseUnavailableException $e) {
    $respondUnavailable(503);

    return;
} catch (PDOException $e) {
    error_log('Recent fragment query failed - ' . $e->getMessage());
    $respondUnavailable(500);

    return;
}

$body = Format::repositoryRows(
    $activity['repositories'],
    time(),
    Config::getInstance()->getGithubUrl(),
    $activity['sparklines']
);
$etag = '"' . hash('xxh3', $body) . '"';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
header('ETag: ' . $etag);

// A cache or Apache's mod_deflate can turn the validator into W/"..." or "...-gzip" on the way back.
$candidates = preg_split('/\s*,\s*/', trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

foreach ($candidates === false ? [] : $candidates as $candidate) {
    if ($candidate === '*' || preg_replace('/^W\/|-(?:gzip|br)(?="$)/', '', $candidate) === $etag) {
        http_response_code(304);

        return;
    }
}

echo $body;
