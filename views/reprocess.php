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

$github_storage_path = Psalm\Spirit\GithubData::getPullRequestStoragePath($git_commit_hash);

if (!file_exists($github_storage_path)) {
	throw new \UnexpectedValueException('No data from GitHub');
}

$psalm_storage_path = Psalm\Spirit\PsalmData::getStoragePath($git_commit_hash);

if (!file_exists($psalm_storage_path)) {
	throw new \UnexpectedValueException('No data from Psalm CI');
}

$gh_payload = json_decode(file_get_contents($github_storage_path), true);

Psalm\Spirit\Sender::send(
	Psalm\Spirit\Auth::getToken(
		$gh_payload['repository']['owner']['login'],
		$gh_payload['repository']['name']
	),
	$gh_payload,
	json_decode(file_get_contents($psalm_storage_path), true)
);