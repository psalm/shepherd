<?php

$payload = json_decode(file_get_contents('php://input'), true);

$git_commit_hash = $payload['git']['head']['id'] ?? null;

if (!$git_commit_hash) {
	throw new \UnexpectedValueException('No git commit hash given');
}

$storage_path = __DIR__ . '/database/commits/' . $git_commit_hash . '.json';

if (file_exists($storage_path)) {
	exit;
}

file_put_contents($storage_path, $payload);

error_log('Telemetry received for ' . $git_commit_hash);
