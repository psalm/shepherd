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

		$diff = $client
			->api('pull_request')
			->configure('diff', 'v3')
			->show(
				$repository_owner,
				$repository,
				$pull_request_number
			);

		$head_sha = $github_data['pull_request']['head']['sha'];
		$base_sha = $github_data['pull_request']['base']['sha'];

		$diff_url = 'http://github.com/' . $repository_owner . '/' . $repository . '/compare/' . $base_sha . '...' . $head_sha . '.diff';

		var_dump($diff_url);

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
        $diff = curl_exec($ch);

        // Close cURL session handle
        curl_close($ch);

        var_dump($diff);

		/** @var array<int, array{severity: string, line_from: int, line_to: int, type: string, message: string,
	     * 		file_name: string, file_path: string, snippet: string, from: int, to: int,
	     * 		snippet_from: int, snippet_to: int, column_from: int, column_to: int, selected_text: string}>
	     */
		$issues = $psalm_data['issues'];

		$file_comments = [];

		foreach ($issues as $issue) {
			if ($issue['severity'] === 'error') {
				$file_comments[] = [
					'path' => $issue['file_name'],
					'position' => $issue['line_from'],
					'body' => $issue['message'],
				];
			}
		}

		var_dump($file_comments);

		$client
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
	}
}