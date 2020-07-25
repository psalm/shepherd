<?php

namespace Psalm\Shepherd;

use Github\Client;

class GithubApi
{
    private const DEFAULT_GITHUB_BRANCH = 'master';

    public static function fetchDefaultBranch(
        Model\GithubRepository $repository
    ): string {
        $client = static::createAuthenticatedClient($repository);

        $response = $client->repository()->show($repository->owner_name, $repository->repo_name);
        /** @var string $default_branch */
        $default_branch = $response['default_branch'] ?? static::DEFAULT_GITHUB_BRANCH;

        return $default_branch;
    }

    public static function fetchPullRequestData(
        Model\GithubRepository $repository,
        int $pr_number
    ): array {
        $client = static::createAuthenticatedClient($repository);

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

    private static function createAuthenticatedClient(Model\GithubRepository $repository): Client
    {
        $config = Config::getInstance();
        $github_token = Auth::getToken($repository);

        $client = new Client(null, null, $config->gh_enterprise_url);
        $client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

        return $client;
    }
}
