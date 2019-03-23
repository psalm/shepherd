<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

require 'vendor/autoload.php';

$payload_data = $_POST['payload'];

$config_path = __DIR__ . '/config.json';

if (!file_exists($config_path)) {
    throw new \UnexpectedValueException('Missing config');
}

/**
 * @var array{github_webhook_secret?:string}
 */
$config = json_decode(file_get_contents($config_path), true);

if (!empty($config['github_webhook_secret'])) {
	$hash = hash_hmac('sha1', $payload_data, $config['github_webhook_secret']);

	if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception('Missing signature header');
	}

	if ($hash !== $_SERVER['HTTP_X_HUB_SIGNATURE']) {
		var_dump($hash, $_SERVER['HTTP_X_HUB_SIGNATURE']);
		throw new \Exception('Mismatching signature');
	}
}

$payload = json_decode($payload_data, true);

$git_commit_hash = $payload['pull_request']['head']['sha'] ?? null;

if (!isset($payload['pull_request'])) {
	if (($payload['ref'] ?? '') === 'refs/heads/master' && isset($payload['repository'])) {
		Psalm\Spirit\GithubData::storeMasterData(
			$git_commit_hash,
			$payload
		);
	}

	return;
}

if (!$git_commit_hash) {
	return;
}

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
	throw new \UnexpectedValueException('Bad git commit hash given');
}

Psalm\Spirit\GithubData::storePullRequestData(
	$git_commit_hash,
	$payload
);
