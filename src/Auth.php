<?php

namespace Psalm\Shepherd;

use function http_build_query;
use function curl_init;
use function curl_setopt;
use const CURLOPT_RETURNTRANSFER;
use const CURLINFO_HEADER_OUT;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_HTTPHEADER;
use function strlen;
use function curl_exec;
use function curl_close;
use function json_decode;
use function is_array;
use function is_string;
use function preg_match;

final class Auth
{
    public static function getToken(Model\GithubRepository $repository) : string
    {
        $config = Config::getInstance();

        if ($config instanceof Config\Custom) {
            return $config->personal_token;
        }

        $repo_token = self::getTokenForRepo($repository);

        if ($repo_token !== null) {
            return $repo_token;
        }

        if ($config->public_access_oauth_token !== null && $config->public_access_oauth_token !== '') {
            return $config->public_access_oauth_token;
        }

        throw new \UnexpectedValueException(
            'Could not find valid token for ' . $repository->owner_name . '/' . $repository->repo_name
        );
    }

    /**
     * @psalm-suppress UnusedParam
     * @psalm-pure
     */
    private static function getTokenForRepo(Model\GithubRepository $repository) : ?string
    {
        return null;
    }

    /**
     * The returned token is checked against GitHub's token format, so it is safe to put in a cookie.
     *
     * @psalm-taint-escape cookie
     */
    public static function fetchTokenFromGithub(string $code, string $state, Config\OAuthApp $config) : string
    {
        $params = [
            'client_id' => $config->client_id,
            'client_secret' => $config->client_secret,
            'code' => $code,
            'state' => $state,
        ];

        $payload = http_build_query($params);

        $github_url = $config->getGithubUrl();

        // Prepare new cURL resource
        $ch = curl_init($github_url . '/login/oauth/access_token');
        if ($ch === false) {
            throw new \RuntimeException('Could not initialise cURL');
        }

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
        $token = is_array($response_data) ? ($response_data['access_token'] ?? null) : null;

        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_]+$/', $token)) {
            throw new \UnexpectedValueException('GitHub did not return a valid access token');
        }

        return $token;
    }
}
