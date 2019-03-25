<?php

require '../vendor/autoload.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

$config = Psalm\Spirit\Config::getInstance();

$raw_post = file_get_contents('php://input');

switch ($_SERVER['CONTENT_TYPE']) {
	case 'application/x-www-form-urlencoded':
		$raw_payload = $_POST['payload'];
		break;

	case 'application/json':
		$raw_payload = $raw_post;
		break;

	default:
		throw new \UnexpectedValueException('Unrecognised payload');
}

if ($config instanceof Psalm\Spirit\Config\Custom && $config->webhook_secret) {
	$hash = 'sha1=' . hash_hmac('sha1', $raw_post, $config->webhook_secret, false);

	if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception('Missing signature header');
	}

	if (!hash_equals($hash, $_SERVER['HTTP_X_HUB_SIGNATURE'])) {
		throw new \Exception('Mismatching signature');
	}
}

$payload = json_decode($raw_payload, true);

if (!isset($payload['pull_request'])) {
	if (($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '') === 'push'
		&& ($payload['ref'] ?? '') === 'refs/heads/master'
		&& isset($payload['repository'])
	) {
		Psalm\Spirit\GithubData::storeMasterData(
			$payload['after'],
			$payload
		);
	}

	return;
}

$git_commit_hash = $payload['pull_request']['head']['sha'] ?? null;

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
