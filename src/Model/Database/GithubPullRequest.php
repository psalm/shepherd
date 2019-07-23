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

    /**
     * @param array{owner_name: string, repo_name: string, number: int, git_commit: string, branch: string, url: string} $database_data
     */
    public static function fromDatabaseData(array $database_data) : self
    {
        return new self(
            new GithubRepository(
                $database_data['owner_name'],
                $database_data['repo_name']
            ),
            $database_data['number'],
            $database_data['git_commit'],
            $database_data['branch'],
            $database_data['url']
        );
    }
}