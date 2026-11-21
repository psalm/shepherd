<?php

namespace Psalm\Shepherd;

use PDO;
use PDOException;
use function array_fill;
use function array_reverse;
use function count;
use function current;
use function explode;
use function implode;
use function krsort;
use function number_format;
use function preg_match;
use function strpos;
use function strtolower;
use function strtotime;

/**
 * @psalm-type HistoryRow = array{commit: string, coverage: float, expressions: int, created_at: int}
 * @psalm-type HistoryPayload = array{t: list<int>, c: list<float>, e: list<int>, s: list<string>}
 * @psalm-type RecentRepository = array{
 *     name: string, commit: string, reported_at: int, coverage: float, level: int|null, expressions: int,
 *     points: list<float>
 * }
 * @psalm-type RecentActivity = array{repositories: list<RecentRepository>, sparklines: bool}
 */
final class Api
{
    private const RECENT_TTL = 60;
    private const RECENT_LIMIT = 12;
    private const RECENT_CANDIDATES = 16;
    private const SPARKLINE_POINTS = 24;

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

    /**
     * The repositories with the most recently received report, newest first, for the home page (cached 60 s).
     *
     * Order is by github_master_commits.id of a repository's latest report row, i.e. "last report received",
     * not by the commit's own date (created_on is supplied by the payload and can be old or skewed).
     * The newest report of each repository gives its coverage, level, commit and time received. With sparklines enabled
     * the 24 newest reports (oldest first) are returned as points. They are enabled only when the optional
     * index repo_recent (owner_name, repo_name, id) exists on github_master_commits, because without it
     * a repository with few reports makes its branch of the query scan the table.
     *
     * @return RecentActivity
     * @throws DatabaseUnavailableException|PDOException when there is neither a database nor a cached copy
     */
    public static function getRecentActivity() : array
    {
        /** @var RecentActivity */
        return FileCache::remember(
            'home-recent-v2',
            self::RECENT_TTL,
            static fn() : array => self::loadRecentActivity()
        );
    }

    /** @return RecentActivity */
    private static function loadRecentActivity() : array
    {
        $connection = DatabaseProvider::getConnection();

        $sparklines = (bool) $connection->query(
            'SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = \'github_master_commits\'
                AND INDEX_NAME = \'repo_recent\'
                LIMIT 1'
        )->fetchColumn();

        // A few spare candidates: repositories without any report are skipped below.
        $candidates = $connection->query(
            'SELECT owner_name, repo_name, MAX(id) AS last_id
                FROM github_master_commits
                GROUP BY owner_name, repo_name
                ORDER BY last_id DESC
                LIMIT ' . self::RECENT_CANDIDATES
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$candidates) {
            return ['repositories' => [], 'sparklines' => $sparklines];
        }

        // The join sits inside each branch so that LIMIT counts reports, not commits that never got one.
        // With the index present it is forced: repo_history also serves this ORDER BY, and a cost tie would
        // otherwise be settled by index definition order.
        // psalm_reports.created_on is set by MySQL (DEFAULT CURRENT_TIMESTAMP), so MySQL converts it to epoch.
        $branch = 'SELECT m.id, m.owner_name, m.repo_name, m.git_commit, UNIX_TIMESTAMP(r.created_on) AS reported_at,
                    r.mixed_count, r.nonmixed_count, r.level
                FROM github_master_commits m' . ($sparklines ? ' USE INDEX (repo_recent)' : '')
                . '
                INNER JOIN psalm_reports r ON r.git_commit = m.git_commit
                WHERE m.owner_name = ? AND m.repo_name = ?
                ORDER BY m.id DESC
                LIMIT ' . ($sparklines ? self::SPARKLINE_POINTS : 1);

        $stmt = $connection->prepare('(' . implode(') UNION ALL (', array_fill(0, count($candidates), $branch)) . ')');

        $params = [];

        /** @var array{owner_name: string, repo_name: string} $candidate */
        foreach ($candidates as $candidate) {
            $params[] = $candidate['owner_name'];
            $params[] = $candidate['repo_name'];
        }

        $stmt->execute($params);

        $by_repository = [];

        /**
         * @var array{
         *     id: int|string, owner_name: string, repo_name: string, git_commit: string, reported_at: int|string,
         *     mixed_count: int|string, nonmixed_count: int|string, level: int|string|null
         * } $row
         */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // MySQL compares names case-insensitively, so group on the lowercased name.
            $by_repository[strtolower($row['owner_name'] . '/' . $row['repo_name'])][(int) $row['id']] = $row;
        }

        $repositories = [];

        foreach ($candidates as $candidate) {
            $name = $candidate['owner_name'] . '/' . $candidate['repo_name'];

            $reports = $by_repository[strtolower($name)] ?? null;

            if ($reports === null) {
                continue;
            }

            krsort($reports);

            $points = [];
            $latest = current($reports);

            if ($latest === false) {
                continue;
            }

            foreach ($reports as $report) {
                $nonmixed = (int) $report['nonmixed_count'];
                $total = (int) $report['mixed_count'] + $nonmixed;
                $points[] = $total ? 100.0 * (float) $nonmixed / (float) $total : 0.0;
            }

            $repositories[] = [
                'name' => $name,
                'commit' => $latest['git_commit'],
                'reported_at' => (int) $latest['reported_at'],
                'coverage' => $points[0],
                'level' => $latest['level'] === null ? null : (int) $latest['level'],
                'expressions' => (int) $latest['mixed_count'] + (int) $latest['nonmixed_count'],
                'points' => array_reverse($points),
            ];

            if (count($repositories) === self::RECENT_LIMIT) {
                break;
            }
        }

        return ['repositories' => $repositories, 'sparklines' => $sparklines];
    }
}
