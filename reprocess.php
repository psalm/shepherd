<?php

ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

require 'vendor/autoload.php';

$git_commit_hash = $_GET['sha'] ?? '';

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
	throw new \UnexpectedValueException('Bad git commit hash given');
}

$github_storage_path = __DIR__ . '/database/github_data/' . $git_commit_hash . '.json';

if (!file_exists($github_storage_path)) {
	throw new \UnexpectedValueException('No data from GitHub');
}

$psalm_storage_path = __DIR__ . '/database/psalm_data/' . $git_commit_hash . '.json';

if (!file_exists($psalm_storage_path)) {
	throw new \UnexpectedValueException('No data from Psalm CI');
}

Psalm\Spirit\Sender::send(
	json_decode(file_get_contents($github_storage_path), true),
	json_decode(file_get_contents($psalm_storage_path), true)
);