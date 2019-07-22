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

	public static function getRepositoryForCommitAndPayload(string $git_commit_hash, array $payload) : ?Model\GithubRepository
	{
		if (!empty($payload['build']['CI_REPO_OWNER'])
			&& !empty($payload['build']['CI_REPO_NAME'])
		) {
			if (empty($payload['build']['CI_PR_REPO_OWNER'])
				&& empty($payload['build']['CI_PR_REPO_NAME'])
				&& ($payload['build']['CI_BRANCH'] ?? '') === 'master'
			) {
				return new Model\GithubRepository(
					$payload['build']['CI_REPO_OWNER'],
					$payload['build']['CI_REPO_NAME']
				);
			}

			if (!empty($payload['build']['CI_PR_REPO_OWNER'])
				&& !empty($payload['build']['CI_PR_REPO_NAME'])
				&& $payload['build']['CI_PR_REPO_OWNER'] === $payload['build']['CI_REPO_OWNER']
				&& $payload['build']['CI_PR_REPO_NAME'] === $payload['build']['CI_REPO_NAME']
			) {
				return new Model\GithubRepository(
					$payload['build']['CI_REPO_OWNER'],
					$payload['build']['CI_REPO_NAME']
				);
			}
		}

		$github_master_storage_path = self::getMasterStoragePath($git_commit_hash);

		if (file_exists($github_master_storage_path)) {
			$github_master_storage_data = json_decode(file_get_contents($github_master_storage_path), true);

			return new Model\GithubRepository(
				$github_master_storage_data['repository']['owner']['login'],
				$github_master_storage_data['repository']['name']
			);
		}

		return null;
	}

	public static function getPullRequestForCommitAndPayload(
		string $git_commit_hash,
		Model\GithubRepository $repository,
		array $payload
	) : ?Model\Database\GithubPullRequest {
		$github_pr_storage_path = self::getPullRequestStoragePath($git_commit_hash);

		if (!file_exists($github_pr_storage_path)) {
			if (!empty($payload['build']['CI_PR_NUMBER'])
				&& $payload['build']['CI_PR_NUMBER'] !== "false"
			) {
				$pr_number = (int) $payload['build']['CI_PR_NUMBER'];

				$data = GithubApi::fetchPullRequestData(
					$repository,
					$pr_number
				);

				self::storePullRequestData($git_commit_hash, $data);
			}
		}

		if (file_exists($github_pr_storage_path)) {
			$gh_pr_data = json_decode(file_get_contents($github_pr_storage_path), true);

			return Model\Database\GithubPullRequest::fromGithubData($gh_pr_data);
		}

		return null;
	}

	private static function getMasterStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_master_data/' . $git_commit_hash . '.json';
	}

	private static function getPullRequestStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_pr_data/' . $git_commit_hash . '.json';
	}
}