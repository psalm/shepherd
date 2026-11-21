<?php

namespace Psalm\Shepherd;

use PDOException;
use function dirname;
use function error_log;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function flock;
use function fopen;
use function is_array;
use function json_decode;
use function json_encode;
use function rename;
use function sys_get_temp_dir;
use function tempnam;
use function time;
use function touch;
use function unlink;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * A small JSON file cache in the system temp dir, shared by all PHP workers of a host.
 *
 * - A copy younger than the TTL is returned as is.
 * - Otherwise exactly one request refreshes it (flock); concurrent requests serve the stale copy meanwhile.
 *   Without any copy they wait for the refresher instead of all hitting the database.
 * - If the refresh fails because the database is down, the stale copy is served and its timestamp is bumped,
 *   so the next attempt happens after another TTL, not on every request. With no copy the exception propagates.
 * - Writes go to a temp file that is renamed over the cache file, so readers never see a partial file.
 */
final class FileCache
{
    /**
     * @param callable(): array<string, mixed> $load
     * @return array<string, mixed>
     * @throws DatabaseUnavailableException|PDOException when the load fails and there is no stale copy
     */
    public static function remember(string $key, int $ttl, callable $load) : array
    {
        $path = sys_get_temp_dir() . '/shepherd-' . $key . '.json';

        $fresh = self::readFresh($path, $ttl);

        if ($fresh !== null) {
            return $fresh;
        }

        $stale = self::read($path);
        $lock = fopen($path . '.lock', 'c');

        if ($lock === false) {
            return self::refresh($path, $load, $stale);
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                if ($stale !== null) {
                    return $stale;
                }

                flock($lock, LOCK_EX);
            }

            // Another request may have refreshed the file while this one waited for the lock.
            $fresh = self::readFresh($path, $ttl);

            return $fresh ?? self::refresh($path, $load, $stale);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param callable(): array<string, mixed> $load
     * @param array<string, mixed>|null $stale
     * @return array<string, mixed>
     */
    private static function refresh(string $path, callable $load, ?array $stale) : array
    {
        try {
            $data = $load();
        } catch (DatabaseUnavailableException | PDOException $e) {
            if ($stale === null) {
                throw $e;
            }

            error_log('Serving stale cache ' . $path . ': ' . $e->getMessage());
            touch($path);

            return $stale;
        }

        $tmp = tempnam(dirname($path), 'shepherd-tmp-');

        if ($tmp !== false) {
            if (file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR)) === false || !rename($tmp, $path)) {
                unlink($tmp);
            }
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private static function readFresh(string $path, int $ttl) : ?array
    {
        $mtime = @filemtime($path);

        return $mtime !== false && time() - $mtime < $ttl ? self::read($path) : null;
    }

    /** @return array<string, mixed>|null */
    private static function read(string $path) : ?array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
