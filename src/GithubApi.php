<?php

namespace Psalm\Shepherd;

use Github\Client;
use function error_log;

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

        error_log(
            'Fetching pull request data for '
                . $repository->owner_name
                . '/' . $repository->repo_name
                . '/' . $pr_number
        );

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
        $client->authenticate($github_token, null, \Github\Client::AUTH_ACCESS_TOKEN);

        return $client;
    }

    public static function fetchPsalmIssuesData() : array
    {
        $query = 'query { 
          repository(owner: "vimeo", name: "psalm") {
            openIssues: issues(states: OPEN, last: 100) {
              nodes {
                bodyText,
                comments(first: 3) {
                  nodes {
                    bodyText,
                    author {
                      login
                    }
                  }
                }
              }
            }
          }
        }';

        $client = static::createAuthenticatedClient(new Model\GithubRepository('vimeo', 'psalm'));
        return $client->api('graphql')->execute($query);
    }
}
