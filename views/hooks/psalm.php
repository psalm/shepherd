<?php

require '../../vendor/autoload.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '1');

error_reporting(E_ALL);

$payload = json_decode(Psalm\Shepherd\Config::readFile('php://input'), true);

if (!is_array($payload)) {
    throw new UnexpectedValueException('Payload is not valid JSON');
}

$git_commit_hash = $payload['git']['head']['id'] ?? null;

if (!is_string($git_commit_hash) || $git_commit_hash === '') {
    throw new UnexpectedValueException('No git commit hash given');
}

if (!preg_match('/^[a-f0-9]+$/', $git_commit_hash)) {
    throw new UnexpectedValueException('Bad git commit hash given');
}

Psalm\Shepherd\PsalmData::handlePayload($git_commit_hash, $payload);
