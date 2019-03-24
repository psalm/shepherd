<?php

require '../../vendor/autoload.php';

$config = Psalm\Spirit\Config::getInstance();

if (!$config instanceof Psalm\Spirit\Config\OAuthApp) {
	throw new \UnexpectedValueException('Cannot use oauth flow if config not proper');
}

$expected_state = hash('sha256', $_SERVER['REMOTE_IP'], $config->client_secret);

$state = $_GET['state'] ?? null;
$code = $_GET['code'] ?? null;

if ($state !== $expected_state) {
	throw new \UnexpectedValueException('States should match');
}

if (!$code) {
	throw new \UnexpectedValueException('No code sent');
}

Auth::fetchTokenFromGithub($code, $state, $config);

