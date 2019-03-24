<?php

namespace Psalm\Spirit;

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

		$github_master_storage_path = GithubData::getMasterStoragePath($git_commit_hash);

		if (file_exists($github_master_storage_path)) {
			$gh_master_data = json_decode(file_get_contents($github_master_storage_path), true);

			self::storeMasterData(
				$git_commit_hash,
				$gh_master_data['repository']['full_name']
			);
		}

		$github_pr_storage_path = GithubData::getPullRequestStoragePath($git_commit_hash);

		if (file_exists($github_pr_storage_path)) {
			$gh_pr_data = json_decode(file_get_contents($github_pr_storage_path), true);

			Sender::send(
				Auth::getToken($gh_pr_data['repository']['owner']['login'], $gh_pr_data['repository']['name']),
				$gh_pr_data,
				$payload
			);
		}
	}

	public static function storeMasterData(string $git_commit_hash, string $repository) : void
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

	public static function getMasterStoragePath(string $repository, string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/psalm_master_data/' . $repository . '/' . $git_commit_hash . '.json';
	}

	public static function getStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/psalm_data/' . $git_commit_hash . '.json';
	}
}