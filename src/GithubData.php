<?php

namespace Psalm\Spirit;

class GithubData
{
	public static function storePullRequestData(string $git_commit_hash, array $payload) : void
	{
		$github_storage_path = self::getPullRequestStoragePath($git_commit_hash);

		if (file_exists($github_storage_path)) {
			exit;
		}

		if (!is_writable(dirname($github_storage_path))) {
			throw new \UnexpectedValueException('Directory should be writable');
		}

		file_put_contents($github_storage_path, json_encode($payload));

		error_log('GitHub PR data saved for ' . $git_commit_hash);

		$psalm_storage_path = PsalmData::getStoragePath($git_commit_hash);

		if (file_exists($psalm_storage_path)) {
			Sender::send(
				Auth::getToken($payload['repository']['owner']['login'], $payload['repository']['name']),
				$payload,
				json_decode(file_get_contents($psalm_storage_path), true)
			);
		}
	}

	public static function storeMasterData(string $git_commit_hash, array $payload) : void
	{
		$github_storage_path = self::getMasterStoragePath($git_commit_hash);

		if (file_exists($github_storage_path)) {
			exit;
		}

		if (!is_writable(dirname($github_storage_path))) {
			throw new \UnexpectedValueException('Directory should be writable');
		}

		file_put_contents($github_storage_path, json_encode($payload));

		error_log('GitHub data saved for ' . $git_commit_hash . ' in ' . $github_storage_path);
	}

	public static function getMasterStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_master_data/' . $git_commit_hash . '.json';
	}

	public static function getPullRequestStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_pr_data/' . $git_commit_hash . '.json';
	}

	public static function fetchPullRequestDataForCommit(
		string $git_commit_hash,
		string $repo_owner,
		string $repo_name,
		int $pr_number
	) : void {
		$config = Config::getInstance();
		$github_token = Auth::getToken($repo_owner, $repo_name);

		$client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

		$pr = $client
		    ->api('pull_request')
		    ->show(
		    	$repo_owner,
		    	$repo_name,
		    	$pr_number
		    );

		$repo = $client
		    ->api('repo')
		    ->show(
		    	$repo_owner,
		    	$repo_name
		    );

		$data = [
			'pull_request' => $pr,
			'repository' => $repo
		];

		self::storePullRequestData($git_commit_hash, $data);
	}
}