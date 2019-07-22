<?php

namespace Psalm\Shepherd;

class GithubApi
{
	public static function fetchPullRequestData(
		Model\GithubRepository $repository,
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
}
