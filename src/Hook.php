<?php

namespace Psalm\Shepherd;

use function header;
use function http_response_code;
use function ini_set;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;

/**
 * Shared request handling for the endpoints CI tools post their results to
 */
final class Hook
{
    /**
     * Rejects anything but a POST with a JSON object naming a commit. Errors are reported as plain text with
     * a 4xx status: CI clients show the status, and an HTML stack trace would expose server paths.
     *
     * @return array{string, array}
     */
    public static function readPayload() : array
    {
        ini_set('display_errors', '0');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            self::reject(405, 'This endpoint only accepts POST requests');
        }

        $payload = json_decode(Config::readFile('php://input'), true);

        if (!is_array($payload)) {
            self::reject(400, 'Payload is not valid JSON');
        }

        $git_commit_hash = $payload['git']['head']['id'] ?? null;

        if (!is_string($git_commit_hash) || $git_commit_hash === '') {
            self::reject(400, 'No git commit hash given');
        }

        if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
            self::reject(400, 'Bad git commit hash given');
        }

        return [$git_commit_hash, $payload];
    }

    public static function reject(int $status, string $message) : never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');

        exit($message . "\n");
    }
}
