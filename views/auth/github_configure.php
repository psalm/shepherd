<?php

require '../../vendor/autoload.php';

$config = Psalm\Spirit\Config::getInstance();

if (!$config instanceof Psalm\Spirit\Config\OAuthApp) {
	throw new \UnexpectedValueException('Cannot use oauth flow if config not proper');
}


$github_token = $_COOKIE['github_token'] ?? null;

if (!$github_token) {
	throw new \UnexpectedValueException('Invalid token');
}

$client = new \Github\Client(null, null, $config->gh_enterprise_url);
$client->authenticate($github_token, null, \Github\Client::AUTH_HTTP_TOKEN);

$repos = $client
    ->api('me')
    ->setPerPage(200)
    ->repositories(
    	'all',
    	'full_name',
    	'asc',
    	'public'
    );

/** @psalm-suppress ForbiddenCode */
var_dump($repos);