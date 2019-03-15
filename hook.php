<?php

require 'vendor/autoload.php';

$payload = json_decode($_POST['payload'], true);

if (!isset($payload['pull_request'])) {
	return;
}

echo 'got here' . PHP_EOL;

$git_commit_hash = $payload['pull_request']['head']['sha'] ?? null;

if (!$git_commit_hash) {
	return;
}

echo 'and here' . PHP_EOL;

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
	throw new \UnexpectedValueException('Bad git commit hash given');
}

$github_storage_path = __DIR__ . '/database/github_data/' . $git_commit_hash . '.json';

if (file_exists($github_storage_path)) {
	exit;
}

echo 'and even here' . PHP_EOL;

file_put_contents($github_storage_path, json_encode($payload));

echo 'and then saved contents to ' . $github_storage_path . PHP_EOL;

error_log('GitHub data received for ' . $git_commit_hash);

$psalm_storage_path = __DIR__ . '/database/psalm_data/' . $git_commit_hash . '.json';

if (file_exists($psalm_storage_path)) {
	Psalm\Spirit\Sender::send(
		$payload,
		json_decode(file_get_contents($psalm_storage_path), true)
	);
}
