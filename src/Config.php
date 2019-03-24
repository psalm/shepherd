<?php

namespace Psalm\Spirit;

abstract class Config
{
	/** @var ?string */
	public $gh_enterprise_url;

	/** @var null|Config\Custom|Config\OAuthApp */
	private static $config;

	/** @return Config\Custom|Config\OAuthApp */
	public static function getInstance() : Config
	{
		if (self::$config) {
			return self::$config;
		}

		$config_path = __DIR__ . '/../config.json';

        if (!file_exists($config_path)) {
            throw new \UnexpectedValueException('Missing config');
        }

        /**
         * @var array{
         *     oauth_app?: array{
         *         client_id: string,
         *         client_secret: string
         *     },
         *     custom?: array{
         *         personal_token: string
         *     },
         *     host?: string
         * }
         */
        $config = json_decode(file_get_contents($config_path), true);

        if (isset($config['custom']['personal_token'])) {
        	return self::$config = new Config\Custom(
        		$config['custom']['personal_token'],
        		$config['custom']['webhook_secret'] ?? null,
	       		$config['gh_enterprise_url'] ?? null
	       	);
        }

       	if (isset($config['oauth_app']['client_id']) && isset($config['oauth_app']['client_secret'])) {
       		return self::$config = new Config\OAuthApp(
	       		$config['oauth_app']['client_id'],
	       		$config['oauth_app']['client_secret'],
	       		$config['gh_enterprise_url'] ?? null,
	       		$config['oauth_app']['db_host'],
	       		$config['oauth_app']['db_name'],
	       		$config['oauth_app']['db_user'],
	       		$config['oauth_app']['db_password']
	       	);
       	}

       	throw new \UnexpectedValueException('Invalid config');
	}
}