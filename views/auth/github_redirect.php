<?php

require '../../vendor/autoload.php';

$config = Psalm\Spirit\Config::getInstance();

if (!$config instanceof Psalm\Spirit\Config\OAuthApp) {
	throw new \UnexpectedValueException('Cannot use oauth flow if config not proper');
}

$expected_state = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'], $config->client_secret);

$state = $_GET['state'] ?? null;
$code = $_GET['code'] ?? null;

if ($state !== $expected_state) {
	throw new \UnexpectedValueException('States should match');
}

if (!$code) {
	throw new \UnexpectedValueException('No code sent');
}

$github_token = Psalm\Spirit\Auth::fetchTokenFromGithub($code, $state, $config);

setcookie('github_token', $github_token);

header('Location: https://' . $_SERVER['HTTP_HOST'] . '/auth/github/configure');