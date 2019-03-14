<?php

require 'vendor/autoload.php';

$payload = json_decode(file_get_contents('php://input'), true);

$git_commit_hash = $payload['git']['head']['id'] ?? null;

if (!$git_commit_hash) {
	throw new \UnexpectedValueException('No git commit hash given');
}

$psalm_storage_path = __DIR__ . '/database/psalm_data/' . $git_commit_hash . '.json';

if (file_exists($psalm_storage_path)) {
	exit;
}

file_put_contents($psalm_storage_path, json_encode($payload));

error_log('Telemetry received for ' . $git_commit_hash);

$github_storage_path = __DIR__ . '/database/github_data/' . $git_commit_hash . '.json';

if (file_exists($github_storage_path)) {
	Psalm\Spirit\Sender::send(
		json_decode(file_get_contents($github_storage_path)),
		$payload
	);
}

