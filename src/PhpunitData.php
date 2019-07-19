<?php

namespace Psalm\Shepherd;

use PDO;

class PhpunitData
{
    public static function handleFailurePayload(string $git_commit, array $payload) : void
    {
        $test_names = $payload['tests'];

        $repository = GithubData::getRepositoryForCommitAndPayload($git_commit, $payload);

        if (!$repository) {
            error_log('No repository found for ' . $git_commit);
            return;
        }

        $gh_pr_data = GithubData::getPullRequestDataForCommitAndPayload($git_commit, $repository, $payload);

        if ($gh_pr_data) {
            $github_pull_request = GithubPullRequest::fromGithubData($gh_pr_data);

            $branch = $github_pull_request->branch;

            foreach ($test_names as $test_name) {
                if (self::hasRegisteredTestFailureForCommit($git_commit, $test_name)) {
                    continue;
                }

                self::registerTestFailureForCommit(
                    $git_commit,
                    $test_name,
                    $repository->owner_name . '/' . $repository->repo_name,
                    $branch
                );
            }

            Sender::addGithubReview(
                'phpunit',
                Auth::getToken($repository),
                $github_pull_request,
                self::getGithubReviewForCommitAndBranch(
                    $git_commit,
                    $branch,
                    $repository->owner_name . '/' . $repository->repo_name
                )
            );
        }
    }

    private static function getGithubReviewForCommitAndBranch(
        string $git_commit,
        string $branch,
        string $repository
    ) : GithubReview {
        $flaky_tests = [];
        $repeated_failure_tests = [];
        $first_time_failures = [];

        $test_failures = self::getTestFailures($git_commit, $branch, $repository);

        if (!$test_failures) {
            throw new \UnexpectedValueException('Could not find any test failures for ' . $git_commit . ', ' . $branch . ' and ' . $repository);
        }

        foreach ($test_failures as $test_name) {
            if (self::hasFailedBeforeOnOtherBranches($test_name, $branch, $git_commit, $repository)) {
                $flaky_tests[] = $test_name;
            } elseif (self::hasFailedBeforeOnBranch($test_name, $branch, $git_commit, $repository)) {
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
        string $branch,
        string $git_commit,
        string $repository
    ) : bool {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT COUNT(*)
                FROM test_failures
                WHERE test_name = :test_name
                AND branch != :branch
                AND git_commit != :git_commit
                AND repository = :repository'
        );

        $stmt->bindValue(':test_name', $test_name);
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':repository', $repository);

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    private static function hasFailedBeforeOnBranch(
        string $test_name,
        string $branch,
        string $git_commit,
        string $repository
    ) : bool {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT COUNT(*)
                FROM test_failures
                WHERE test_name = :test_name
                AND branch = :branch
                AND git_commit != :git_commit
                AND repository = :repository'
        );

        $stmt->bindValue(':test_name', $test_name);
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':repository', $repository);

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    private static function getTestFailures(string $git_commit, string $branch, string $repository) : array
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT test_name
                FROM test_failures
                WHERE git_commit = :git_commit
                AND branch = :branch
                AND repository = :repository'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':repository', $repository);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
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

        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    private static function registerTestFailureForCommit(
        string $git_commit,
        string $test_name,
        ?string $repository,
        string $branch
    ) : void {
        $connection = DatabaseProvider::getConnection();

        error_log('Registering test failure for ' . $test_name . PHP_EOL);

        $stmt = $connection->prepare('
            INSERT IGNORE into test_failures (repository, git_commit, branch, test_name)
                VALUES (:repository, :git_commit, :branch, :test_name)'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':branch', $branch);
        $stmt->bindValue(':repository', $repository);
        $stmt->bindValue(':test_name', $test_name);

        $stmt->execute();
    }
}