<?php

require 'vendor/autoload.php';

$payload = json_decode($_POST['payload'], true);

if (!isset($payload['pull_request'])) {
	return;
}

$git_commit_hash = $payload['pull_request']['head']['sha'] ?? null;

if (!$git_commit_hash) {
	return;
}

$github_storage_path = __DIR__ . '/database/github_data/' . $git_commit_hash . '.json';

if (file_exists($github_storage_path)) {
	exit;
}

file_put_contents($github_storage_path, json_encode($payload));

error_log('GitHub data received for ' . $git_commit_hash);

$psalm_storage_path = __DIR__ . '/database/psalm_data/' . $git_commit_hash . '.json';

if (file_exists($psalm_storage_path)) {
	Psalm\Spirit\Sender::send(
		$payload,
		json_decode(file_get_contents($psalm_storage_path))
	);
}
