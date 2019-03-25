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

		$repo_token = self::getTokenForRepo($repo_owner, $repo_name);

		if ($repo_token) {
			return $repo_token;
		}

		if ($config->public_access_oauth_token) {
			return $config->public_access_oauth_token;
		}

		throw new \UnexpectedValueException('Could not find valid token for ' . $repo_owner . '/' . $repo_name);
	}

	private static function getTokenForRepo(string $_repo_owner, string $_repo_name) : ?string
	{
		return null;
	}

	public static function fetchTokenFromGithub(string $code, string $state, Config\OAuthApp $config) : string
	{
		$params = [
		    'client_id' => $config->client_id,
		    'client_secret' => $config->client_secret,
		    'code' => $code,
		    'state' => $state,
		];

		$payload = http_build_query($params);

		$github_url = $config->gh_enterprise_url ?: 'https://github.com';

		// Prepare new cURL resource
		$ch = curl_init($github_url . '/login/oauth/access_token');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		// Set HTTP Header for POST request
		curl_setopt(
		    $ch,
		    CURLOPT_HTTPHEADER,
		    [
		        'Accept: application/json',
		        'Content-Type: application/x-www-form-urlencoded',
		        'Content-Length: ' . strlen($payload)
		    ]
		);

		// Submit the POST request
		$response = (string) curl_exec($ch);

		// Close cURL session handle
		curl_close($ch);

		if (!$response) {
		    throw new \UnexpectedValueException('Response should exist');
		}

		$response_data = json_decode($response, true);

		return $response_data['access_token'];
	}
}