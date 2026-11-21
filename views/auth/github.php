<?php

require '../../vendor/autoload.php';

$config = Psalm\Shepherd\Config::getInstance();

if (!$config instanceof Psalm\Shepherd\Config\OAuthApp) {
    throw new UnexpectedValueException('Cannot use oauth flow if config not proper');
}

// No redirect_uri: GitHub then uses the app's registered callback URL. Building it from the Host header would let
// a client choose it.
$params = [
    'client_id' => $config->client_id,
    'allow_signup' => false,
    'scope' => 'repo write:repo_hook read:org',
    'state' => hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', $config->client_secret)
];

$github_url = $config->getGithubUrl();

header('Location: ' . $github_url . '/login/oauth/authorize?' . http_build_query($params));
