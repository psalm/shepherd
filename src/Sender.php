<?php

namespace Psalm\Spirit;

class Sender
{
	public static function send(array $github_data, array $psalm_data) : void
	{
		$config_path = __DIR__ . '/../config.json';

		if (!file_exists($config_path)) {
			throw new \UnexpectedValueException('Missing config');
		}

		/**
		 * @var array{reviewer: array{user: string, password: string}}
		 */
		$config = json_decode(file_get_contents($config_path), true);

		$client = new \Github\Client();
		$client->authenticate($config['reviewer']['user'], $config['reviewer']['password'], \Github\Client::AUTH_HTTP_PASSWORD);

		$repository = $github_data['repository']['name'];
		$repository_owner = $github_data['repository']['owner']['login'];
		$pull_request_number = $github_data['pull_request']['number'];

		$head_sha = $github_data['pull_request']['head']['sha'];
		$base_sha = $github_data['pull_request']['base']['sha'];

        $pr_path = dirname(__DIR__) . '/database/pr_comments/' . parse_url($github_data['pull_request']['html_url'], PHP_URL_PATH);

        if (file_exists($pr_path)) {
            $comment = json_decode(file_get_contents($pr_path), true);

            $client
                ->api('pull_request')
                ->comments()
                ->remove(
                    $repository_owner,
                    $repository,
                    $comment['id']
                );
        }

		$diff_url = 'https://github.com/'
			. $repository_owner . '/'
			. $repository . '/compare/'
			. substr($base_sha, 0, 8) . '...' . substr($head_sha, 0, 8) . '.diff';

		// Prepare new cURL resource
		$ch = curl_init($diff_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);

		// Set HTTP Header for POST request
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			[
				'Accept: application/vnd.github.v3.diff'
			]
		);

		// Submit the POST request
		$diff_string = curl_exec($ch);

		// Close cURL session handle
		curl_close($ch);

		$diff_parser = new \SebastianBergmann\Diff\Parser();
		$diffs = $diff_parser->parse($diff_string);

		/** @var array<int, array{severity: string, line_from: int, line_to: int, type: string, message: string,
		 * 		file_name: string, file_path: string, snippet: string, from: int, to: int,
		 * 		snippet_from: int, snippet_to: int, column_from: int, column_to: int, selected_text: string}>
		 */
		$issues = $psalm_data['issues'];

		$file_comments = [];

		foreach ($issues as $issue) {
			if ($issue['severity'] === 'error') {
				$file_name = $issue['file_name'];

				foreach ($diffs as $diff) {
					if ($diff->getTo() === 'b/' . $file_name) {
						$diff_file_offset = 0;

						foreach ($diff->getChunks() as $chunk) {
							$chunk_end = $chunk->getEnd();
							$chunk_end_range = $chunk->getEndRange();

                            if ($issue['line_from'] >= $chunk_end
								&& $issue['line_from'] < $chunk_end + $chunk_end_range
							) {
								$line_offset = 0;
								foreach ($chunk->getLines() as $i => $chunk_line) {
									$diff_file_offset++;

									if ($chunk_line->getType() !== \SebastianBergmann\Diff\Line::REMOVED) {
										$line_offset++;
									}

									if ($issue['line_from'] === $line_offset + $chunk_end - 1) {
										$file_comments[] = [
											'path' => $file_name,
											'position' => $diff_file_offset,
											'body' => $issue['message'],
										];
										break 3;
									}
								}
							} else {
								$diff_file_offset += count($chunk->getLines());
							}
						}
					}
				}
			}
		}

		$comment = $client
			->api('pull_request')
			->reviews()
			->create(
				$repository_owner,
				$repository,
				$pull_request_number,
				[
					'commit_id' => $head_sha,
					'event' => 'COMMENT',
					'body' => 'Psalm has thoughts',
					'comments' => $file_comments,
				]
			);

        $pr_path_dir = dirname($pr_path);

        mkdir($pr_path_dir, 0777, true);

        file_put_contents($pr_path, json_encode($comment));
	}
}