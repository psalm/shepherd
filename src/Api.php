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
                INNER JOIN git_master_commits ON `git_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `git_master_commits`.`created_on` DESC'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return number_format(100 * $row['nonmixed_count'] / ($row['mixed_count'] + $row['nonmixed_count']), 1);
    }

    public static function getHistory(string $repository) : array
    {
        list($owner_name, $repo_name) = explode('/', $repository);

        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT `git_master_commits`.`git_commit`, mixed_count, nonmixed_count, `git_master_commits`.created_on
                FROM psalm_reports
                INNER JOIN git_master_commits ON `git_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                WHERE owner_name = :owner_name
                AND repo_name = :repo_name
                ORDER BY `git_master_commits`.`created_on` DESC'
        );

        $stmt->bindValue(':owner_name', $owner_name);
        $stmt->bindValue(':repo_name', $repo_name);

        $stmt->execute();

        $history = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!$row['mixed_count'] && $row['nonmixed_count']) {
                $c = 100;
            } else {
                $c = 100 * $row['nonmixed_count'] / ($row['mixed_count'] + $row['nonmixed_count']);
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
            'SELECT owner_name, repo_name, max(`psalm_reports`.created_on) as last_updated
                FROM psalm_reports
                INNER JOIN git_master_commits ON `git_master_commits`.`git_commit` = `psalm_reports`.`git_commit`
                GROUP BY owner_name, repo_name
                ORDER BY last_updated DESC
                LIMIT 5'
        );

        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $repositories[] = $row['owner_name'] . '/' . $row['repo_name'];
        }

        return $repositories;
    }
}