<?php

namespace Psalm\Shepherd;

use PDO;
use function explode;
use function number_format;
use function preg_match;
use function strpos;

/**
 * @psalm-type HistoryRow = array{commit: string, coverage: float, total: int, created_at: int}
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
                ORDER BY `github_master_commits`.`created_on` DESC'
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
                ORDER BY `github_master_commits`.`created_on` DESC'
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
     * Extracts the "owner/repo" name from the raw query string the history routes receive,
     * e.g. "vimeo/psalm" or "vimeo/psalm&page=2" (the rewrite rules append extra params with QSA).
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
     * All reports of a repository, newest first. Rows sharing a second are all kept.
     * created_at is a UTC unix timestamp.
     *
     * @return list<HistoryRow>
     */
    public static function getHistory(string $repository) : array
    {
        return self::fetchHistory($repository, null, 0);
    }

    /**
     * One page of reports of a repository, newest first.
     *
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

    /** @return list<HistoryRow> */
    private static function fetchHistory(string $repository, ?int $limit, int $offset) : array
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT `github_master_commits`.`git_commit`,
                    mixed_count,
                    nonmixed_count,
                    UNIX_TIMESTAMP(`github_master_commits`.created_on) AS created_at
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

        /** @var array{git_commit: string, mixed_count: int|string, nonmixed_count: int|string, created_at: int|string} $row */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nonmixed = (int) $row['nonmixed_count'];
            $total = (int) $row['mixed_count'] + $nonmixed;

            $history[] = [
                'commit' => $row['git_commit'],
                'coverage' => $total ? 100.0 * (float) $nonmixed / (float) $total : 0.0,
                'total' => $total,
                'created_at' => (int) $row['created_at'],
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
