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

	public static function getMasterStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_master_data/' . $git_commit_hash . '.json';
	}

	public static function getPullRequestStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/github_pr_data/' . $git_commit_hash . '.json';
	}

	private static function fetchPullRequestData(
		GithubRepository $repository,
		int $pr_number
	) : array {
		$config = Config::getInstance();
		$github_token = Auth::getToken($repository);

		$client = new \Github\Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        error_log('Fetching pull request data for ' . $repository->owner_name . '/' . $repository->repo_name . '/' . $pr_number);

		$pr = $client
		    ->api('pull_request')
		    ->show(
		    	$repository->owner_name,
		    	$repository->repo_name,
		    	$pr_number
		    );

		return [
			'pull_request' => $pr,
		];
	}

	public static function getRepositoryForCommitAndPayload(string $git_commit_hash, array $payload) : ?GithubRepository
	{
		if (!empty($payload['build']['CI_REPO_OWNER'])
			&& !empty($payload['build']['CI_REPO_NAME'])
		) {
			if (empty($payload['build']['CI_PR_REPO_OWNER'])
				&& empty($payload['build']['CI_PR_REPO_NAME'])
				&& ($payload['build']['CI_BRANCH'] ?? '') === 'master'
			) {
				return new GithubRepository(
					$payload['build']['CI_REPO_OWNER'],
					$payload['build']['CI_REPO_NAME']
				);
			}

			if (!empty($payload['build']['CI_PR_REPO_OWNER'])
				&& !empty($payload['build']['CI_PR_REPO_NAME'])
				&& $payload['build']['CI_PR_REPO_OWNER'] === $payload['build']['CI_REPO_OWNER']
				&& $payload['build']['CI_PR_REPO_NAME'] === $payload['build']['CI_REPO_NAME']
			) {
				return new GithubRepository(
					$payload['build']['CI_REPO_OWNER'],
					$payload['build']['CI_REPO_NAME']
				);
			}
		}

		$github_master_storage_path = GithubData::getMasterStoragePath($git_commit_hash);

		if (file_exists($github_master_storage_path)) {
			$github_master_storage_data = json_decode(file_get_contents($github_master_storage_path), true);

			return new GithubRepository(
				$github_master_storage_data['repository']['owner']['login'],
				$github_master_storage_data['repository']['name']
			);
		}

		return null;
	}

	public static function getPullRequestDataForCommitAndPayload(
		string $git_commit_hash,
		GithubRepository $repository,
		array $payload
	) : ?array {
		$github_pr_storage_path = self::getPullRequestStoragePath($git_commit_hash);

		if (!file_exists($github_pr_storage_path)) {
			if (!empty($payload['build']['CI_PR_NUMBER'])
				&& $payload['build']['CI_PR_NUMBER'] !== "false"
			) {
				$pr_number = (int) $payload['build']['CI_PR_NUMBER'];

				$data = self::fetchPullRequestData(
					$repository,
					$pr_number
				);

				self::storePullRequestData($git_commit_hash, $data);
			}
		}

		if (file_exists($github_pr_storage_path)) {
			return json_decode(file_get_contents($github_pr_storage_path), true);
		}

		return null;
	}
}