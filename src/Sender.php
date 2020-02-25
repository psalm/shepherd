<?php

namespace Psalm\Shepherd;

use PDO;

class Sender
{
    public static function getGithubPullRequestDiff(string $github_token, Model\Database\GithubPullRequest $pull_request) : string
    {
        $config = Config::getInstance();

        $client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        $repository = $pull_request->repository->owner_name . '/' . $pull_request->repository->repo_name;

        try {
            $diff_string = $client
                ->api('pull_request')
                ->configure('diff', 'v3')
                ->show(
                    $pull_request->repository->owner_name,
                    $pull_request->repository->repo_name,
                    $pull_request->number
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not fetch pull request diff for ' . $pull_request->number . ' on ' . $repository
            );
        }

        if (!is_string($diff_string)) {
            throw new \UnexpectedValueException('$diff_string should be a string');
        }

        return $diff_string;
    }

    public static function addGithubReview(
        string $review_type,
        string $github_token,
        Model\Database\GithubPullRequest $pull_request,
        Model\GithubReview $github_review
    ) : void {
        $config = Config::getInstance();

        $client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        $review_id = self::getGithubReviewIdForPullRequest($pull_request->url, $review_type);
        $comment_id = self::getGithubCommentIdForPullRequest($pull_request->url, $review_type);

        if ($review_id) {
            // deletes review comments
            self::deleteCommentsForReview($client, $pull_request, $review_id);
        }

        if ($comment_id) {
            // deletes the review itself
            self::deleteComment($client, $pull_request, $comment_id);
        }

        if ($github_review->file_comments) {
            error_log('Adding Github file comments on ' . $pull_request->url);

            self::addGithubReviewComments($client, $pull_request, $review_type, $github_review->file_comments);
        }

        if ($review_id || $comment_id || !$github_review->checks_passed) {
            error_log('Adding Github Review on ' . $pull_request->url);

            self::addGithubReviewComment($client, $pull_request, $review_type, $github_review->message);
        }
    }

    private static function deleteCommentsForReview(
        \Github\Client $client,
        Model\Database\GithubPullRequest $pull_request,
        int $review_id
    ) : void {
        $repository = $pull_request->repository;
        $repository_slug = $repository->owner_name . '/' . $repository->repo_name;

        try {
            $comments = $client
                ->api('pull_request')
                ->reviews()
                ->comments(
                    $repository->owner_name,
                    $repository->repo_name,
                    $pull_request->number,
                    $review_id
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not fetch comments for review ' . $review_id . ' for pull request ' . $pull_request->number . ' on ' . $repository_slug
            );
        }

        if (is_array($comments)) {
            foreach ($comments as $comment) {
                try {
                    $client
                        ->api('pull_request')
                        ->comments()
                        ->remove(
                            $repository->owner_name,
                            $repository->repo_name,
                            $comment['id']
                        );
                } catch (\Github\Exception\RuntimeException $e) {
                    error_log(
                        'Could not remove PR comment (via PR API) ' . $comment['id'] . ' on ' . $repository_slug
                    );
                }
            }
        }
    }

    private static function deleteComment(
        \Github\Client $client,
        Model\Database\GithubPullRequest $pull_request,
        int $comment_id
    ) : void {
        $repository = $pull_request->repository;
        $repository_slug = $repository->owner_name . '/' . $repository->repo_name;

        try {
            $client
                ->api('issue')
                ->comments()
                ->remove(
                    $repository->owner_name,
                    $repository->repo_name,
                    $comment_id
                );
        } catch (\Github\Exception\RuntimeException $e) {
            error_log(
                'Could not remove PR comment (via issues API) ' . $comment_id . ' on ' . $repository_slug
            );
        }
    }

    private static function addGithubReviewComments(
        \Github\Client $client,
        Model\Database\GithubPullRequest $pull_request,
        string $tool,
        array $file_comments
    ) : void {
        $repository = $pull_request->repository;
        $repository_slug = $repository->owner_name . '/' . $repository->repo_name;

        try {
            $review = $client
                ->api('pull_request')
                ->reviews()
                ->create(
                    $repository->owner_name,
                    $repository->repo_name,
                    $pull_request->number,
                    [
                        'commit_id' => $pull_request->head_commit,
                        'body' => '',
                        'comments' => $file_comments,
                        'event' => 'REQUEST_CHANGES',
                    ]
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not create PR review for ' . $pull_request->number . ' on ' . $repository_slug
            );
        }

        self::storeGithubReviewForPullRequest($pull_request->url, $tool, $review['id']);
    }

    private static function addGithubReviewComment(
        \Github\Client $client,
        Model\Database\GithubPullRequest $pull_request,
        string $tool,
        string $message_body
    ) : void {
        $repository = $pull_request->repository;
        $repository_slug = $repository->owner_name . '/' . $repository->repo_name;

        try {
            $comment = $client
                ->api('issue')
                ->comments()
                ->create(
                    $repository->owner_name,
                    $repository->repo_name,
                    $pull_request->number,
                    [
                        'body' => $message_body,
                    ]
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not add comment for ' . $pull_request->number . ' on ' . $repository_slug
            );
        }

        self::storeGithubCommentForPullRequest($pull_request->url, $tool, $comment['id']);
    }

    private static function storeGithubReviewForPullRequest(
        string $github_pr_url,
        string $tool,
        int $github_review_id
    ) : void {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'DELETE FROM github_pr_reviews where github_pr_url = :github_pr_url and tool = :tool'
        );

        $stmt->bindValue(':github_pr_url', $github_pr_url);
        $stmt->bindValue(':tool', $tool);

        $stmt->execute();

        $stmt = $connection->prepare(
            'INSERT INTO github_pr_reviews (github_pr_url, tool, github_review_id)
                VALUES (:github_pr_url, :tool, :github_review_id)'
        );

        $stmt->bindValue(':github_pr_url', $github_pr_url);
        $stmt->bindValue(':tool', $tool);
        $stmt->bindValue(':github_review_id', $github_review_id);

        $stmt->execute();
    }

    private static function storeGithubCommentForPullRequest(
        string $github_pr_url,
        string $tool,
        int $github_comment_id
    ) : void {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'INSERT INTO github_pr_comments (github_pr_url, tool, github_comment_id)
                VALUES (:github_pr_url, :tool, :github_comment_id)'
        );

        $stmt->bindValue(':github_pr_url', $github_pr_url);
        $stmt->bindValue(':tool', $tool);
        $stmt->bindValue(':github_comment_id', $github_comment_id);

        $stmt->execute();
    }

    private static function getGithubReviewIdForPullRequest(
        string $github_pr_url,
        string $tool
    ) : ?int {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT github_review_id
                FROM github_pr_reviews
                WHERE github_pr_url = :github_pr_url
                AND tool = :tool'
        );

        $stmt->bindValue(':github_pr_url', $github_pr_url);
        $stmt->bindValue(':tool', $tool);

        $stmt->execute();

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private static function getGithubCommentIdForPullRequest(
        string $github_pr_url,
        string $tool
    ) : ?int {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'SELECT github_comment_id
                FROM github_pr_comments
                WHERE github_pr_url = :github_pr_url
                AND tool = :tool'
        );

        $stmt->bindValue(':github_pr_url', $github_pr_url);
        $stmt->bindValue(':tool', $tool);

        $stmt->execute();

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }
}
