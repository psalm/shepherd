<?php
namespace Psalm\Shepherd\Model\Database;

use Psalm\Shepherd\Model\GithubRepository;

class GithubPullRequest
{
    /** @var GithubRepository */
    public $repository;

    /** @var int */
    public $number;

    /** @var string */
    public $branch;

    /** @var string */
    public $head_commit;

    /** @var string */
    public $url;

    public function __construct(
        GithubRepository $repository,
        int $number,
        string $head_commit,
        string $branch,
        string $url
    ) {
        $this->repository = $repository;
        $this->number = $number;
        $this->head_commit = $head_commit;
        $this->branch = $branch;
        $this->url = $url;
    }

    public static function fromGithubData(array $github_data) : self
    {
        return new self(
            new GithubRepository(
                $github_data['pull_request']['base']['repo']['owner']['login'],
                $github_data['pull_request']['base']['repo']['name']
            ),
            $github_data['pull_request']['number'],
            $github_data['pull_request']['head']['sha'],
            $github_data['pull_request']['head']['ref'],
            $github_data['pull_request']['html_url']
        );
    }
}