<?php

namespace Psalm\Shepherd;

use Github\Client;
use function error_log;
use function array_filter;
use function strpos;
use function array_values;
use function explode;
use function substr;
use function trim;
use function json_decode;
use function file_get_contents;
use function str_ireplace;
use function implode;
use function array_map;
use function strtoupper;

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

    public static function fetchPsalmIssuesData(?string $after) : array
    {
        $query = 'query($afterCursor: String) {
            repository(owner: "vimeo", name: "psalm") {
                issues(states: OPEN, first: 30, after: $afterCursor) {
                    pageInfo {
                        startCursor
                        hasNextPage
                        endCursor
                    }
                    nodes {
                        number,
                        bodyText,
                        comments(first: 3) {
                            nodes {
                                body,
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

        $different_issues = [];

        $data = $client->api('graphql')->execute($query, ['afterCursor' => $after])['data'];

        $db_config = json_decode(dirname(__DIR__) . '/config.json')['mysql_psalm_dev'];

        try {
            $pdo = new PDO($db_config['dsn'], $db_config['user'], $db_config['password']);
        } catch (PDOException $e) {
            die('Connection to database failed');
        }

        foreach ($data['repository']['issues']['nodes'] as $issue) {
            foreach ($issue['comments']['nodes'] as $comment) {
                if ($comment['author']['login'] === 'psalm-github-bot'
                    && strpos($comment['body'], 'I found these snippets:') !== false
                ) {
                    $body = $comment['body'];

                    $lines = array_values(
                        array_filter(
                            explode("\n", $body),
                            function ($line) {
                                return $line !== 'I found these snippets:'
                                    && $line !== '<details>'
                                    && $line !== '</details>';
                            }
                        )
                    );

                    $link = null;

                    $in_php = false;
                    $in_results = false;

                    $psalm_result = '';

                    $psalm_results = [];

                    $old_commit = '';

                    foreach ($lines as $line) {
                        if (strpos($line, '<summary>') === 0) {
                            $link = substr($line, 9, -10);
                            continue;
                        }

                        if (strpos($line, 'Psalm output') === 0) {
                            $old_commit_pos = strpos($line, 'Psalm output (using commit ');

                            if ($old_commit_pos !== false) {
                                $old_commit = substr(trim($line), 27, -1);
                            }
                            continue;
                        }

                        if ($line === '```php') {
                            $in_php = true;
                            continue;
                        }

                        if ($line === '```') {
                            if ($in_php) {
                                $in_php = false;
                                continue;
                            }

                            if ($in_results) {
                                $in_results = false;

                                if ($link === null) {
                                    throw new \UnexpectedValueException('No link');
                                }

                                $psalm_results[$link] = trim($psalm_result);

                                continue;
                            }

                            $in_results = true;
                            $psalm_result = '';
                            continue;
                        }

                        if ($in_results) {
                            $psalm_result .= $line . "\n";
                        }
                    }

                    foreach ($psalm_results as $link => $psalm_result) {
                        $data = ['hash' => $hash, 'result_cache' => $psalm_result, 'cache_commit' => $old_commit ?: null];

                        $insert_sql = 'UPDATE `codes` SET `result_cache` = :result_cache, `cache_commit` = :cache_commit WHERE `hash` = :hash LIMIT 1';
                        $stmt = $pdo->prepare($insert_sql);
                        $stmt->execute($data);

                        $current_result = self::formatSnippetResult(
                            json_decode(
                                file_get_contents($link . '/results'),
                                true
                            ) ?: []
                        );

                        $current_result_normalised = str_ireplace(
                            [
                                'class, interface or enum named',
                                ' or the value is not used',
                                'Variable $'
                            ],
                            ['', '', '$'],
                            $current_result
                        );

                        $psalm_result_normalised = str_ireplace(
                            [
                                'class or interface',
                                'class, interface or enum named',
                                ' or the value is not used',
                                'Variable $'
                            ],
                            ['', '', '', '$'],
                            $psalm_result
                        );

                        if ($current_result_normalised !== $psalm_result_normalised) {
                            $different_issues[$issue['number']][$link] = [$current_result, $psalm_result];
                        }
                    }

                    continue;
                }
            }
        }

        return [$different_issues, $data['repository']['issues']['pageInfo']['endCursor']];
    }

    private static function formatSnippetResult(array $data): string
    {
        if ($data['results'] === null) {
            return '';
        }

        if ($data['results'] === []) {
            return 'No issues!';
        }

        return implode(
            "\n\n",
            array_map(
                function (array $issue) {
                    return strtoupper($issue['severity'])
                        . ': ' . $issue['type']
                        . ' - ' .$issue['line_from']
                        . ':' . $issue['column_from']
                        . ' - ' . $issue['message'];
                },
                $data['results']
            )
        );
    }
}
