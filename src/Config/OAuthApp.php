<?php

namespace Psalm\Spirit\Config;

class OAuthApp extends \Psalm\Spirit\Config
{
	/** @var string */
	public $client_id;

	/** @var string */
	public $client_secret;

	public function __construct(string $client_id, string $client_secret, ?string $gh_enterprise_url)
	{
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->gh_enterprise_url = $gh_enterprise_url;
	}
}