<?php

namespace Psalm\Shepherd;

class GithubRepository
{
    /** @var string */
    public $owner_name;

    /** @var string */
    public $repo_name;

    public function __construct(string $owner_name, string $repo_name)
    {
        $this->owner_name = $owner_name;
        $this->repo_name = $repo_name;
    }
}
