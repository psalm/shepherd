<?php

namespace Psalm\Spirit\Config;

class OAuthApp extends \Psalm\Spirit\Config
{
	/** @var string */
	public $client_id;

	/** @var string */
	public $client_secret;

	/** @var string */
	public $db_host;

	/** @var string */
	public $db_name;

	/** @var string */
	public $db_user;

	/** @var string */
	public $db_password;

	/** @var ?string */
	public $public_access_oauth_token;

	public function __construct(
		string $client_id,
		string $client_secret,
		?string $gh_enterprise_url,
		?string $public_access_oauth_token,
		string $db_host,
		string $db_name,
		string $db_user,
		string $db_password
	) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->gh_enterprise_url = $gh_enterprise_url;
		$this->public_access_oauth_token = $public_access_oauth_token;
		$this->db_host = $db_host;
		$this->db_name = $db_name;
		$this->db_user = $db_user;
		$this->db_password = $db_password;
	}
}