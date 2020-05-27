<?php

namespace Psalm\Shepherd;

use PDO;

class Api
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

        $fraction = $row['nonmixed_count'] / ($row['mixed_count'] + $row['nonmixed_count']);

        if ($fraction >= 0.9995) {
        	return '100';
        }

        return number_format(100 * $fraction, 1);
    }

    public static function getHistory(string $repository) : array
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT `github_master_commits`.`git_commit`, mixed_count, nonmixed_count, `github_master_commits`.created_on
                FROM psalm_reports
                INNER JOIN github_master_commits ON `github_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `github_master_commits`.`created_on` DESC'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        $history = [];

        /** @var array{git_commit: string, mixed_count: int, nonmixed_count: int, created_on: string} $row */
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $total = ($row['mixed_count'] + $row['nonmixed_count']);
            if (!$row['mixed_count'] && $row['nonmixed_count']) {
                $c = 100;
            } elseif($total) {
                $c = 100 * $row['nonmixed_count'] / $total;
            } else {
                $c = 0;
            }

            $history[$row['created_on']] = [$row['git_commit'], $c];
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
