<?php

namespace Psalm\Shepherd;

class PsalmData
{
    public static function handlePayload(string $git_commit, array $payload) : void
    {
        if (!empty($payload['build']['CI_REPO_OWNER'])
            && !empty($payload['build']['CI_REPO_NAME'])
            && empty($payload['build']['CI_PR_REPO_OWNER'])
            && empty($payload['build']['CI_PR_REPO_NAME'])
            && ($payload['build']['CI_BRANCH'] ?? '') === 'master'
        ) {
            $repository = new Model\GithubRepository(
                $payload['build']['CI_REPO_OWNER'],
                $payload['build']['CI_REPO_NAME']
            );

            GithubData::setRepositoryForMasterCommit($git_commit, $repository);
        }

        self::savePsalmData($git_commit, $payload['issues'], $payload['coverage'][0], $payload['coverage'][1]);

        error_log('Telemetry saved for ' . $git_commit);

        $repository = GithubData::getRepositoryForCommitAndPayload($git_commit, $payload);

        if (!$repository) {
            exit();
        }

        $github_pull_request = GithubData::getPullRequestForCommitAndPayload($git_commit, $repository, $payload);

        if ($github_pull_request) {
            $token = Auth::getToken($repository);

            Sender::addGitHubReview(
                'psalm',
                $token,
                $github_pull_request,
                self::getGithubReviewForIssues(
                    $payload['issues'],
                    Sender::getGithubPullRequestDiff($token, $github_pull_request)
                )
            );
        }
    }

    
    private static function savePsalmData(string $git_commit, array $issues, int $mixed_count, int $nonmixed_count) : void
    {
        $connection = DatabaseProvider::getConnection();

        $stmt = $connection->prepare(
            'INSERT IGNORE INTO psalm_reports (`git_commit`, `issues`, `mixed_count`, `nonmixed_count`)
                VALUES (:git_commit, :issues, :mixed_count, :nonmixed_count)'
        );

        $stmt->bindValue(':git_commit', $git_commit);
        $stmt->bindValue(':issues', json_encode($issues));
        $stmt->bindValue(':mixed_count', $mixed_count);
        $stmt->bindValue(':nonmixed_count', $nonmixed_count);

        $stmt->execute();
    }

    /** @param array<int, array{severity: string, line_from: int, line_to: int, type: string, message: string,
     *      file_name: string, file_path: string, snippet: string, from: int, to: int,
     *      snippet_from: int, snippet_to: int, column_from: int, column_to: int, selected_text: string}> $issues
     */
    private static function getGithubReviewForIssues(array $issues, string $diff_string) : Model\GithubReview
    {
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
        } else {
            $message_body = 'Psalm didn’t find any errors!';
        }

        return new Model\GithubReview(
            $message_body,
            !$missed_errors && !$file_comments,
            $file_comments
        );
    }
}