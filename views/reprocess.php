<?php

require '../vendor/autoload.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

$git_commit_hash = $_GET['sha'] ?? '';

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
	throw new \UnexpectedValueException('Bad git commit hash given');
}

$psalm_storage_path = Psalm\Spirit\PsalmData::getStoragePath($git_commit_hash);

if (!file_exists($psalm_storage_path)) {
	throw new \UnexpectedValueException('No data from Psalm CI');
}

$payload = json_decode(file_get_contents($psalm_storage_path), true);

$github_pr_storage_path = Psalm\Spirit\GithubData::getPullRequestStoragePath($git_commit_hash);

if (!file_exists($github_pr_storage_path)) {
	if (!empty($payload['build']['CI_PR_REPO_OWNER'])
		&& !empty($payload['build']['CI_PR_REPO_NAME'])
		&& !empty($payload['build']['CI_PR_NUMBER'])
		&& !empty($payload['build']['CI_REPO_OWNER'])
		&& !empty($payload['build']['CI_REPO_NAME'])
		&& $payload['build']['CI_PR_NUMBER'] !== "false"
	) {
		$owner = $payload['build']['CI_REPO_OWNER'];
		$repo_name = $payload['build']['CI_REPO_NAME'];
		$pr_number = (int) $payload['build']['CI_PR_NUMBER'];

		Psalm\Spirit\GithubData::fetchPullRequestDataForCommit(
			$git_commit_hash,
			$owner,
			$repo_name,
			$pr_number
		);
	}
}