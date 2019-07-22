<?php

namespace Psalm\Shepherd;

class PsalmData
{
	public static function storeJson(string $git_commit_hash, array $payload) : void
	{
		$psalm_storage_path = self::getStoragePath($git_commit_hash);

		if (file_exists($psalm_storage_path)) {
			return;
		}

		if (!is_writable(dirname($psalm_storage_path))) {
			throw new \UnexpectedValueException('Directory should be writable');
		}

		file_put_contents($psalm_storage_path, json_encode($payload));

		error_log('Telemetry saved for ' . $git_commit_hash);

		$repository = GithubData::getRepositoryForCommitAndPayload($git_commit_hash, $payload);

		if (!$repository) {
			return;
		}

		self::storeMasterData(
			$git_commit_hash,
			$repository
		);

		$github_pull_request = GithubData::getPullRequestForCommitAndPayload($git_commit_hash, $repository, $payload);

		if ($github_pull_request) {
			$token = Auth::getToken($repository);

			Sender::updatePsalmReview(
				$token,
				$github_pull_request,
				self::getGithubReviewForIssues(
		        	$payload['issues'],
		        	Sender::getGithubPullRequestDiff($token, $github_pull_request)
		        )
			);
		}
	}

	/** @param array<int, array{severity: string, line_from: int, line_to: int, type: string, message: string,
     *      file_name: string, file_path: string, snippet: string, from: int, to: int,
     *      snippet_from: int, snippet_to: int, column_from: int, column_to: int, selected_text: string}> $issues
     */
    private static function getGithubReviewForIssues(array $issues, string $diff_string) : GithubReview
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

        return new GithubReview(
            $message_body,
            !$missed_errors && !$file_comments,
            $file_comments
        );
    }

	public static function storeMasterData(string $git_commit_hash, GithubRepository $repository) : void
	{
		$psalm_master_storage_path = self::getMasterStoragePath($repository, $git_commit_hash);

		if (file_exists($psalm_master_storage_path)) {
			exit;
		}

		if (!file_exists(dirname($psalm_master_storage_path))) {
			mkdir(dirname($psalm_master_storage_path), 0777, true);
		}

		if (!is_writable(dirname($psalm_master_storage_path))) {
			throw new \UnexpectedValueException('Directory should be writable');
		}

		symlink(self::getStoragePath($git_commit_hash), $psalm_master_storage_path);

		error_log('Psalm master data saved for ' . $git_commit_hash . ' in ' . $psalm_master_storage_path);
	}

	public static function getMasterStoragePath(GithubRepository $repository, string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/psalm_master_data/'
			. strtolower($repository->owner_name . '/' . $repository->repo_name)
			. '/' . $git_commit_hash . '.json';
	}

	public static function getStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/psalm_data/' . $git_commit_hash . '.json';
	}
}