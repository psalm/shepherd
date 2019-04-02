<?php

namespace Psalm\Shepherd;

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

	public static function storeCommitData(string $git_commit_hash, array $payload) : void
	{
		$github_storage_path = self::getCommitStoragePath($git_commit_hash);

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

	public static function getCommitStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_commits/' . $git_commit_hash . '.json';
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

        error_log('Fetching pull request data for ' . $repo_owner . '/' . $repo_name . '/' . $pr_number);

		$pr = $client
		    ->api('pull_request')
		    ->show(
		    	$repo_owner,
		    	$repo_name,
		    	$pr_number
		    );

		$data = [
			'pull_request' => $pr,
		];

		self::storePullRequestData($git_commit_hash, $data);
	}

	public static function fetchDataForCommit(
		string $git_commit_hash,
		string $repo_owner,
		string $repo_name
	) : void {
		$config = Config::getInstance();
		$github_token = Auth::getToken($repo_owner, $repo_name);

		$client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        var_dump('Fetching data for ' . $repo_owner . '/' . $repo_name . '/' . $git_commit_hash);

		$data = $client
		    ->api('git')
		    ->commits()
		    ->show(
		    	$repo_owner,
		    	$repo_name,
		    	$git_commit_hash
		    );

		self::storeCommitData($git_commit_hash, $data);
	}

	public static function getDataForCommit(
		string $git_commit_hash,
		string $repo_owner,
		string $repo_name
	) : array {
		$commit_path = self::getCommitStoragePath($git_commit_hash);

		if (!file_exists($commit_path)) {
			self::fetchDataForCommit($git_commit_hash, $repo_owner, $repo_name);
		}

		return json_decode(file_get_contents($commit_path), true);
	}
}