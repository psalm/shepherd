<?php

namespace Psalm\Shepherd;

use PDO;

class GithubData
{
    public static function storePullRequestData(string $git_commit, array $github_data) : void
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'INSERT IGNORE INTO github_pull_requests (`owner_name`, `repo_name`, `git_commit`, `number`, `branch`, `url`)
                VALUES (:owner_name, :repo_name, :git_commit, :number, :branch, :url)'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':owner_name', $github_data['pull_request']['base']['repo']['owner']['login']);
        $stmt->bindValue(':repo_name', $github_data['pull_request']['base']['repo']['name']);
        $stmt->bindValue(':number', $github_data['pull_request']['number']);
        $stmt->bindValue(':branch', $github_data['pull_request']['head']['ref']);
        $stmt->bindValue(':url', $github_data['pull_request']['html_url']);

        $stmt->execute();

        error_log('GitHub PR data saved for ' . $git_commit);
    }

    public static function storeMasterData(string $git_commit, array $payload) : void
    {
        $repository = new Model\GithubRepository(
            $payload['repository']['owner']['login'],
            $payload['repository']['name']
        );

        self::setRepositoryForMasterCommit(
            $git_commit,
            $repository,
            date('Y-m-d H:i:s', $payload['head']['date'] ?? date('U'))
        );

        error_log('GitHub data saved for ' . $git_commit);
    }

    public static function getRepositoryForCommitAndPayload(string $git_commit_hash, array $payload) : ?Model\GithubRepository
    {
        if (!empty($payload['build']['CI_REPO_OWNER'])
            && !empty($payload['build']['CI_REPO_NAME'])
        ) {
            if (empty($payload['build']['CI_PR_REPO_OWNER'])
                && empty($payload['build']['CI_PR_REPO_NAME'])
                && ($payload['build']['CI_BRANCH'] ?? '') === 'master'
            ) {
                return new Model\GithubRepository(
                    $payload['build']['CI_REPO_OWNER'],
                    $payload['build']['CI_REPO_NAME']
                );
            }

            if (!empty($payload['build']['CI_PR_REPO_OWNER'])
                && !empty($payload['build']['CI_PR_REPO_NAME'])
                && $payload['build']['CI_PR_REPO_OWNER'] === $payload['build']['CI_REPO_OWNER']
                && $payload['build']['CI_PR_REPO_NAME'] === $payload['build']['CI_REPO_NAME']
            ) {
                return new Model\GithubRepository(
                    $payload['build']['CI_REPO_OWNER'],
                    $payload['build']['CI_REPO_NAME']
                );
            }
        }

        if ($repository = self::getRepositoryForPullRequestCommit($git_commit_hash)) {
            return $repository;
        }

        return self::getRepositoryForMasterCommit($git_commit_hash);
    }

    private static function getRepositoryForMasterCommit(string $git_commit) : ?Model\GithubRepository
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT owner_name, repo_name
                FROM github_master_commits
                WHERE git_commit = :git_commit'
        );

        $stmt->bindValue(':git_commit', $git_commit);

        $stmt->execute();

        /** @var array{owner_name: string, repo_name: string}|null */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Model\GithubRepository(
            $row['owner_name'],
            $row['repo_name']
        );
    }

    private static function getRepositoryForPullRequestCommit(string $git_commit) : ?Model\GithubRepository
    {
        $pull_request = self::getPullRequestFromDatabase($git_commit);

        if (!$pull_request) {
            return null;
        }

        return $pull_request->repository;
    }

    public static function setRepositoryForMasterCommit(
        string $git_commit,
        Model\GithubRepository $repository,
        string $created_on
    ) : void {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'INSERT IGNORE INTO github_master_commits (git_commit, owner_name, repo_name, created_on)
                VALUES (:git_commit, :owner_name, :repo_name, :created_on)'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':owner_name', $repository->owner_name);
        $stmt->bindValue(':repo_name', $repository->repo_name);
        $stmt->bindValue(':created_on', $created_on);

        $stmt->execute();
    }

    public static function getPullRequestForCommitAndPayload(
        string $git_commit_hash,
        Model\GithubRepository $repository,
        array $payload
    ) : ?Model\Database\GithubPullRequest {
        $github_pull_request = self::getPullRequestFromDatabase($git_commit_hash);

        if (!$github_pull_request
            && !empty($payload['build']['CI_PR_NUMBER'])
            && $payload['build']['CI_PR_NUMBER'] !== "false"
        ) {
            $pr_number = (int) $payload['build']['CI_PR_NUMBER'];

            self::storePullRequestData(
                $git_commit_hash,
                GithubApi::fetchPullRequestData(
                    $repository,
                    $pr_number
                )
            );

            $github_pull_request = self::getPullRequestFromDatabase($git_commit_hash);
        }

        return $github_pull_request;
    }

    private static function getPullRequestFromDatabase(string $git_commit) : ?Model\Database\GithubPullRequest
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT `owner_name`, `repo_name`, `number`, `git_commit`, `branch`, `url`
                FROM `github_pull_requests`
                WHERE git_commit = :git_commit'
        );

        $stmt->bindValue(':git_commit', $git_commit);

        $stmt->execute();

        /** @var array{owner_name: string, repo_name: string, number: int, git_commit: string, branch: string, url: string}|null */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return Model\Database\GithubPullRequest::fromDatabaseData($row);
    }
}