<?php

namespace Psalm\Spirit;

class Auth
{
	public static function getToken(string $repo_owner, string $repo_name) : string
	{
		$config = Config::getInstance();

		if ($config instanceof Config\Custom) {
			return $config->personal_token;
		}

		return self::getTokenForRepo($repo_owner, $repo_name);
	}

	private static function getTokenForRepo(string $repo_owner, string $repo_name) : string
	{
		return 'hello';
	}
}