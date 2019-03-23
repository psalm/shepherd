<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

require 'vendor/autoload.php';

$payload = json_decode(file_get_contents('php://input'), true);

$git_commit_hash = $payload['git']['head']['id'] ?? null;

if (!$git_commit_hash) {
	throw new \UnexpectedValueException('No git commit hash given');
}

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
	throw new \UnexpectedValueException('Bad git commit hash given');
}

Psalm\Spirit\PsalmData::storeJson($git_commit_hash, $payload);
