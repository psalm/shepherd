<?php

require '../../vendor/autoload.php';

$config = Psalm\Spirit\Config::getInstance();

if (!$config instanceof Psalm\Spirit\Config\OAuthApp) {
	throw new \UnexpectedValueException('Cannot use oauth flow if config not proper');
}

$params = [
	'client_id' => $config->client_id,
	'redirect_uri' => 'https://spirit.psalm.dev/auth/redirect',
];

header('Location: https://github.com/login/oauth/authorize?' . http_build_query($params));