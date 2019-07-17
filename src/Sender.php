<?php

namespace Psalm\Shepherd;

class Sender
{
    public static function updatePsalmReview(
        string $github_token,
        array $github_data,
        array $psalm_data
    ) : void {
        $config = Config::getInstance();

        $client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        $repository = $github_data['pull_request']['base']['repo']['name'];
        $repository_owner = $github_data['pull_request']['base']['repo']['owner']['login'];
        $pull_request_number = $github_data['pull_request']['number'];

        $head_sha = $github_data['pull_request']['head']['sha'];

        $pr_review_path = dirname(__DIR__) . '/database/pr_reviews/' . parse_url($github_data['pull_request']['html_url'], PHP_URL_PATH);
        $pr_comment_path = dirname(__DIR__) . '/database/pr_comments/' . parse_url($github_data['pull_request']['html_url'], PHP_URL_PATH);

        $review = null;

        if (file_exists($pr_review_path)) {
            $review = json_decode(file_get_contents($pr_review_path), true);

            try {
                $comments = $client
                    ->api('pull_request')
                    ->reviews()
                    ->comments(
                        $repository_owner,
                        $repository,
                        $pull_request_number,
                        $review['id']
                    );
            } catch (\Github\Exception\RuntimeException $e) {
                throw new \RuntimeException(
                    'Could not fetch comments for review ' . $review['id'] . ' for pull request ' . $pull_request_number . ' on ' . $repository_owner . '/' . $repository
                );
            }

            if (is_array($comments)) {
                foreach ($comments as $comment) {
                    try {
                        $client
                            ->api('pull_request')
                            ->comments()
                            ->remove(
                                $repository_owner,
                                $repository,
                                $comment['id']
                            );
                    } catch (\Github\Exception\RuntimeException $e) {
                        error_log(
                            'Could not remove PR comment (via PR API) ' . $comment['id'] . ' on ' . $repository_owner . '/' . $repository
                        );
                    }
                }
            }
        }

        if (file_exists($pr_comment_path)) {
            $comment = json_decode(file_get_contents($pr_comment_path), true);

            try {
                $client
                    ->api('issue')
                    ->comments()
                    ->remove(
                        $repository_owner,
                        $repository,
                        $comment['id']
                    );
            } catch (\Github\Exception\RuntimeException $e) {
                error_log(
                    'Could not remove PR comment (via issues API) ' . $comment['id'] . ' on ' . $repository_owner . '/' . $repository
                );
            }
        }

        try {
            $diff_string = $client
                ->api('pull_request')
                ->configure('diff', 'v3')
                ->show(
                    $repository_owner,
                    $repository,
                    $pull_request_number
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not fetch pull request diff for ' . $pull_request_number . ' on ' . $repository_owner . '/' . $repository
            );
        }

        if (!is_string($diff_string)) {
            throw new \UnexpectedValueException('$diff_string should be a string');
        }

        /** @var array<int, array{severity: string, line_from: int, line_to: int, type: string, message: string,
         *      file_name: string, file_path: string, snippet: string, from: int, to: int,
         *      snippet_from: int, snippet_to: int, column_from: int, column_to: int, selected_text: string}>
         */
        $issues = $psalm_data['issues'];

        $file_comments = [];

        $missed_errors = [];

        foreach ($issues as $issue) {
            if ($issue['severity'] !== 'error') {
                continue;
            }

            $file_name = $issue['file_name'];
            $line_from = $issue['line_from'];

            $diff_file_offset = DiffLineFinder::getGitHubPositionFromDiff(
                $line_from,
                $file_name,
                $diff_string
            );

            if ($diff_file_offset !== null) {
                $snippet = $issue['snippet'];
                $selected_text = $issue['selected_text'];

                $selection_start = $issue['from'] - $issue['snippet_from'];
                $selection_length = $issue['to'] - $issue['from'];

                $before_selection = substr($snippet, 0, $selection_start);

                $after_selection = substr($snippet, $selection_start + $selection_length);

                $before_lines = explode("\n", $before_selection);

                $last_before_line_length = strlen(array_pop($before_lines));

                $first_selected_line = explode("\n", $selected_text)[0];

                if ($first_selected_line === $selected_text) {
                    $first_selected_line .= explode("\n", $after_selection)[0];
                }

                $issue_string = $before_selection . $first_selected_line
                    . "\n" . str_repeat(' ', $last_before_line_length) . str_repeat('^', strlen($selected_text));

                $file_comments[] = [
                    'path' => $file_name,
                    'position' => $diff_file_offset,
                    'body' => $issue['message'] . "\n```\n"
                        . $issue_string . "\n```",
                ];

                continue;
            }

            $missed_errors[] = $file_name . ':' . $line_from . ':' . $issue['column_from'] . ' - ' . $issue['message'];
        }

        if ($missed_errors) {
            $comment_text = "\n\n```\n" . implode("\n", $missed_errors) . "\n```";

            if ($file_comments) {
                $message_body = 'Psalm also found errors in other files' . $comment_text;
            } else {
                $message_body = 'Psalm found errors in other files' . $comment_text;
            }
        } elseif ($file_comments) {
            $message_body = 'Psalm found some errors';
        } elseif ($review) {
            $message_body = 'Psalm didn’t find any errors!';
        } else {
            return;
        }

        if ($file_comments) {
            try {
                $review = $client
                    ->api('pull_request')
                    ->reviews()
                    ->create(
                        $repository_owner,
                        $repository,
                        $pull_request_number,
                        [
                            'commit_id' => $head_sha,
                            'body' => '',
                            'comments' => $file_comments,
                            'event' => 'REQUEST_CHANGES',
                        ]
                    );
            } catch (\Github\Exception\RuntimeException $e) {
                throw new \RuntimeException(
                    'Could not create PR review for ' . $pull_request_number . ' on ' . $repository_owner . '/' . $repository
                );
            }

            $pr_review_path_dir = dirname($pr_review_path);

            if (!file_exists($pr_review_path_dir)) {
                mkdir($pr_review_path_dir, 0777, true);
            }

            file_put_contents($pr_review_path, json_encode($review));
        }

        try {
            $comment = $client
                ->api('issue')
                ->comments()
                ->create(
                    $repository_owner,
                    $repository,
                    $pull_request_number,
                    [
                        'body' => $message_body,
                    ]
                );
        } catch (\Github\Exception\RuntimeException $e) {
            throw new \RuntimeException(
                'Could not add comment for ' . $pull_request_number . ' on ' . $repository_owner . '/' . $repository
            );
        }

        $pr_comment_path_dir = dirname($pr_comment_path);

        if (!file_exists($pr_comment_path_dir)) {
            mkdir($pr_comment_path_dir, 0777, true);
        }

        file_put_contents($pr_comment_path, json_encode($comment));
    }

    public static function addGithubReview(
        string $review_type,
        string $github_token,
        GithubPullRequest $pull_request,
        string $message,
        array $file_comments = []
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

        if ($file_comments) {
            self::addGithubReviewComments($client, $pull_request, $review_type, $file_comments);
        }

        self::addGithubReviewComment($client, $pull_request, $review_type, $message);
    }

    private static function deleteCommentsForReview(
        \Github\Client $client,
        GithubPullRequest $pull_request,
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
        GithubPullRequest $pull_request,
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
        GithubPullRequest $pull_request,
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
        GithubPullRequest $pull_request,
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
        int $review_id
    ) : void {

    }

    private static function storeGithubCommentForPullRequest(
        string $github_pr_url,
        string $tool,
        int $comment_id
    ) : void {

    }

    private static function getGithubReviewIdForPullRequest(
        string $github_pr_url,
        string $tool
    ) : ?int {
        return null;
    }

    private static function getGithubCommentIdForPullRequest(
        string $github_pr_url,
        string $tool
    ) : ?int {
        return null;
    }
}
