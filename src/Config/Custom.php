<?php

namespace Psalm\Shepherd\Config;

class Custom extends \Psalm\Shepherd\Config
{
	/** @var string */
	public $personal_token;

	/** @var ?string */
	public $webhook_secret;

	public function __construct(string $personal_token, ?string $webhook_secret, ?string $gh_enterprise_url)
	{
		$this->personal_token = $personal_token;
		$this->webhook_secret = $webhook_secret;
		$this->gh_enterprise_url = $gh_enterprise_url;
	}
}