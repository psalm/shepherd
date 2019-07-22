<?php

namespace Psalm\Shepherd\Model;

class GithubReview
{
	/** @var string */
    public $message;

    /**
     * @var bool
     */
    public $checks_passed;

    /** @var array<int, array{path: string, position: int, body: string}> */
    public $file_comments;

    /**
     * @param array<int, array{path: string, position: int, body: string}> $file_comments
     */
    public function __construct(string $message, bool $checks_passed, array $file_comments = [])
    {
        $this->message = $message;
        $this->checks_passed = $checks_passed;
        $this->file_comments = $file_comments;
    }
}
