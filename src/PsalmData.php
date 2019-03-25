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

		if (!empty($payload['build']['CI_REPO_OWNER'])
			&& !empty($payload['build']['CI_REPO_NAME'])
			&& empty($payload['build']['CI_PR_REPO_OWNER'])
			&& empty($payload['build']['CI_PR_REPO_NAME'])
			&& ($payload['build']['CI_BRANCH'] ?? '') === 'master'
		) {
			self::storeMasterData(
				$git_commit_hash,
				$payload['build']['CI_REPO_OWNER'] . '/' . $payload['build']['CI_REPO_NAME']
			);
		}

		$github_pr_storage_path = GithubData::getPullRequestStoragePath($git_commit_hash);

		if (!file_exists($github_pr_storage_path)) {
			if (isset($payload['build']['CI_PR_REPO_OWNER'])
				&& isset($payload['build']['CI_PR_REPO_NAME'])
				&& isset($payload['build']['CI_PR_NUMBER'])
				&& $payload['build']['CI_PR_NUMBER'] !== "false"
			) {
				$owner = $payload['build']['CI_PR_REPO_OWNER'];
				$repo_name = $payload['build']['CI_PR_REPO_NAME'];
				$pr_number = (int) $payload['build']['CI_PR_NUMBER'];

				GithubData::fetchPullRequestDataForCommit(
					$git_commit_hash,
					$owner,
					$repo_name,
					$pr_number
				);
			}
		}

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