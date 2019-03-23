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
}