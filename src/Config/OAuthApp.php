<?php

namespace Psalm\Shepherd\Config;

class OAuthApp extends \Psalm\Shepherd\Config
{
	/** @var string */
	public $client_id;

	/** @var string */
	public $client_secret;

	/** @var ?string */
	public $public_access_oauth_token;

	/**
     * @param array{dsn: string, user: string, password: string} $mysql
     */
	public function __construct(
		string $client_id,
		string $client_secret,
		?string $gh_enterprise_url,
		?string $public_access_oauth_token,
		array $mysql
	) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->gh_enterprise_url = $gh_enterprise_url;
		$this->public_access_oauth_token = $public_access_oauth_token;
		$this->mysql = $mysql;
	}
}