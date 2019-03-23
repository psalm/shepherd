<?php

namespace Psalm\Spirit;

class PsalmData
{
	public static function storeJson(string $git_commit_hash, array $payload) : void
	{
		$psalm_storage_path = self::getStoragePath($git_commit_hash);

		if (file_exists($psalm_storage_path)) {
			return;
		}

		if (!is_writable(dirname($psalm_storage_path))) {
			throw new \UnexpectedValueException('Directory should be writable');
		}

		file_put_contents($psalm_storage_path, json_encode($payload));

		error_log('Telemetry saved for ' . $git_commit_hash);

		$github_storage_path = GithubData::getPullRequestStoragePath($git_commit_hash);

		if (file_exists($github_storage_path)) {
			Sender::send(
				json_decode(file_get_contents($github_storage_path), true),
				$payload
			);
		}
	}

	public static function getStoragePath(string $git_commit_hash) : string
	{
		return dirname(__DIR__) . '/database/psalm_data/' . $git_commit_hash . '.json';
	}
}