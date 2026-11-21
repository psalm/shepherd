<?php

namespace Psalm\Shepherd;

use PDO;
use function array_reverse;
use function explode;
use function number_format;
use function preg_match;
use function strpos;
use function strtotime;

/**
 * @psalm-type HistoryRow = array{commit: string, coverage: float, expressions: int, created_at: int}
 * @psalm-type HistoryPayload = array{t: list<int>, c: list<float>, e: list<int>, s: list<string>}
 */
final class Api
{
    public static function getTypeCoverage(string $repository) : ?string
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT mixed_count, nonmixed_count
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `github_master_commits`.`created_on` DESC, `github_master_commits`.`id` DESC
                LIMIT 1'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        /** @var array<string, int> */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $total = $row['mixed_count'] + $row['nonmixed_count'];
        if (!$total) {
            return '0';
        }

        $fraction = (float) $row['nonmixed_count'] / (float) $total;

        if ($fraction >= 0.9995) {
            return '100';
        }

        return number_format(100.0 * $fraction, 1);
    }

    public static function getLevel(string $repository) : ?int
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT level
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `github_master_commits`.`created_on` DESC, `github_master_commits`.`id` DESC
                LIMIT 1'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        /** @var array<string, int> */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row['level'];
    }

    /**
     * Extracts and validates the "owner/repo" name from the raw query string the history routes receive,
     * e.g. "vimeo/psalm" or "vimeo/psalm&page=2" (the rewrite rules append extra params with QSA).
     * Returns null when it is not a well-formed repository name.
     * PHP would mangle dots in $_GET keys, so the repository is never read from $_GET.
     */
    public static function extractRepository(string $query_string) : ?string
    {
        $repository = explode('&', $query_string, 2)[0];

        if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/D', $repository) || strpos($repository, '..') !== false) {
            return null;
        }

        return $repository;
    }

    /**
     * All reports of a repository as columns, ascending by time. Ties within one second are ordered by id ASC
     * (the reverse of the id DESC tie-break the table and badges use), which history.js (strictlyIncreasing)
     * relies on to keep same-second points in a stable order.
     * t = unix seconds, c = coverage % (unrounded; rounded only for display), e = expressions, s = commit sha.
     *
     * @return HistoryPayload
     */
    public static function getHistoryPayload(string $repository) : array
    {
        $payload = ['t' => [], 'c' => [], 'e' => [], 's' => []];

        foreach (array_reverse(self::fetchHistory($repository, null, 0)) as $row) {
            $payload['t'][] = $row['created_at'];
            $payload['c'][] = $row['coverage'];
            $payload['e'][] = $row['expressions'];
            $payload['s'][] = $row['commit'];
        }

        return $payload;
    }

    /**
     * One page of reports of a repository, newest first (ties by id DESC, like the badges).
     *
     * @param positive-int $limit
     * @param non-negative-int $offset
     * @return list<HistoryRow>
     */
    public static function getHistoryPage(string $repository, int $limit, int $offset) : array
    {
        return self::fetchHistory($repository, $limit, $offset);
    }

    public static function getHistoryCount(string $repository) : int
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $stmt = DatabaseProvider::getConnection()->prepare(
            'SELECT COUNT(*)
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param positive-int|null $limit
     * @param non-negative-int $offset
     * @return list<HistoryRow>
     */
    private static function fetchHistory(string $repository, ?int $limit, int $offset) : array
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT `github_master_commits`.`git_commit`,
                    mixed_count,
                    nonmixed_count,
                    `github_master_commits`.created_on
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `github_master_commits`.`created_on` DESC, `github_master_commits`.`id` DESC'
                . ($limit !== null ? ' LIMIT :limit OFFSET :offset' : '')
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        $history = [];

        /** @var array{git_commit: string, mixed_count: int|string, nonmixed_count: int|string, created_on: string} $row */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nonmixed = (int) $row['nonmixed_count'];
            $total = (int) $row['mixed_count'] + $nonmixed;

            $history[] = [
                'commit' => $row['git_commit'],
                'coverage' => $total ? 100.0 * (float) $nonmixed / (float) $total : 0.0,
                'expressions' => $total,
                // Same PHP default timezone as the writers (date() in PsalmData/GithubData), so the instant round-trips.
                'created_at' => (int) strtotime($row['created_on']),
            ];
        }

        return $history;
    }

    /** @return string[] */
    public static function getRecentGithubRepositories() : array
    {
        $repositories = [];

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT owner_name, repo_name, max(`github_master_commits`.created_on) as last_updated
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                GROUP BY owner_name, repo_name
                ORDER BY last_updated DESC
                LIMIT 5'
        );

        $stmt->execute();

        /** @var array{owner_name: string, repo_name: string} $row */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $repositories[] = $row['owner_name'] . '/' . $row['repo_name'];
        }

        return $repositories;
    }
}
