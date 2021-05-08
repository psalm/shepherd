<?php

require '../../vendor/autoload.php';

$config = Psalm\Shepherd\Config::getInstance();

if (!$config instanceof Psalm\Shepherd\Config\OAuthApp) {
    throw new UnexpectedValueException('Cannot use oauth flow if config not proper');
}


$github_token = $_COOKIE['github_token'] ?? null;

if (!$github_token) {
    throw new UnexpectedValueException('Invalid token');
}

$client = new \Github\Client(null, null, $config->gh_enterprise_url);
$client->authenticate($github_token, null, \Github\Client::AUTH_ACCESS_TOKEN);

$repos = $client
    ->api('me')
    ->repositories(
        'all',
        'full_name',
        'asc',
        'public'
    );

/** @psalm-suppress ForbiddenCode */
var_dump($repos);
