<?php

namespace Psalm\Shepherd;

use PDO;

class PhpunitData
{
    public static function handleFailurePayload(string $git_commit, array $payload) : void
    {
        $test_names = $payload['tests'];
        $branch_name = $payload['git']['branch'];

        $repository = GithubData::getRepositoryForCommitAndPayload($git_commit, $payload);

        echo('Handling test failure payload' . PHP_EOL);

        foreach ($test_names as $test_name) {
            if (self::hasRegisteredTestFailureForCommit($git_commit, $test_name)) {
                continue;
            }

            self::registerTestFailureForCommit(
                $git_commit,
                $test_name,
                $repository ? $repository->owner_name . '/' . $repository->repo_name : null,
                $branch_name
            );
        }

        if (!$repository) {
            echo('Could not idenfiy repository' . PHP_EOL);
            return;
        }

        $gh_pr_data = GithubData::getPullRequestDataForCommitAndPayload($git_commit, $repository, $payload);

        if ($gh_pr_data) {
            Sender::addGithubReview(
                'phpunit',
                Auth::getToken($repository),
                GithubPullRequest::fromGithubData($gh_pr_data),
                self::getGithubReviewForCommitAndBranch(
                    $git_commit,
                    $branch_name,
                    $repository->owner_name . '/' . $repository->repo_name
                )
            );
        }
    }

    private static function getGithubReviewForCommitAndBranch(
        string $git_commit,
        string $branch_name,
        string $repository
    ) : GithubReview {
        $flaky_tests = [];
        $repeated_failure_tests = [];
        $first_time_failures = [];

        foreach (self::getTestFailures($git_commit, $branch_name, $repository) as $test_name) {
            if (self::hasFailedBeforeOnOtherBranches($test_name, $branch_name, $git_commit, $repository)) {
                $flaky_tests[] = $test_name;
            } elseif (self::hasFailedBeforeOnBranch($test_name, $branch_name, $git_commit, $repository)) {
                $repeated_failure_tests[] = $test_name;
            } else {
                $first_time_failures[] = $test_name;
            }
        }

        $message = '';

        foreach ($flaky_tests as $test_name) {
            $message .= 'PHPUnit test ' . $test_name . ' has failed before in other branches' . PHP_EOL;
        }

        foreach ($repeated_failure_tests as $test_name) {
            $message .= 'PHPUnit test ' . $test_name . ' has failed before in this branch' . PHP_EOL;
        }

        foreach ($first_time_failures as $test_name) {
            $message .= 'PHPUnit test ' . $test_name . ' has never failed before in any branch.' . PHP_EOL;
        }

        return new GithubReview($message, false);
    }

    private static function hasFailedBeforeOnOtherBranches(
        string $test_name,
        string $branch_name,
        string $git_commit,
        string $repository
    ) : bool {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT COUNT(*)
                FROM test_failures
                WHERE test_name = :test_name
                AND branch_name != :branch_name
                AND git_commit != :git_commit
                AND repository = :repository'
        );

        $stmt->bindValue(':test_name', $test_name);
        $stmt->bindValue(':branch_name', $branch_name);
        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':repository', $repository);

        return $stmt->fetchColumn() > 0;
    }

    private static function hasFailedBeforeOnBranch(
        string $test_name,
        string $branch_name,
        string $git_commit,
        string $repository
    ) : bool {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT COUNT(*)
                FROM test_failures
                WHERE test_name = :test_name
                AND branch_name = :branch_name
                AND git_commit != :git_commit
                AND repository = :repository'
        );

        $stmt->bindValue(':test_name', $test_name);
        $stmt->bindValue(':branch_name', $branch_name);
        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':repository', $repository);

        return $stmt->fetchColumn() > 0;
    }

    private static function getTestFailures(string $git_commit, string $branch_name, string $repository) : array
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT test_name, repository
                FROM test_failures
                WHERE git_commit = :git_commit
                AND branch_name = :branch_name
                AND repository = :repository'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':branch_name', $branch_name);
        $stmt->bindValue(':repository', $repository);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function hasRegisteredTestFailureForCommit(string $git_commit, string $test_name) : bool
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT COUNT(*)
                FROM test_failures
                WHERE git_commit = :git_commit
                AND test_name = :test_name'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':test_name', $test_name);

        return $stmt->fetchColumn() > 0;
    }

    private static function registerTestFailureForCommit(
        string $git_commit,
        string $test_name,
        ?string $repository_name,
        string $branch
    ) : void {
        $connection = DatabaseProvider::getConnection();

        echo('Registering test failure for ' . $test_name . PHP_EOL);

        $stmt = $connection->prepare('
            INSERT into test_failures (repository_name, git_commit, branch, test_name)
                VALUES (:repository_name, :git_commit, :branch, :test_name)'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':repository_name', $repository_name);
        $stmt->bindValue(':test_name', $test_name);

        $stmt->execute();
    }
}