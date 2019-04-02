<?php

namespace Psalm\Shepherd;

class Api
{
	public static function getTypeCoverage(string $repository) : ?string
	{
		$repository_data_dir = dirname(__DIR__) . '/database/psalm_master_data/' . $repository;

		if (!file_exists($repository_data_dir)) {
			return null;
		}

		$newest_file_name = self::getOrderedFilesInDir($repository_data_dir)[0];

		$target = readlink($repository_data_dir . '/' . $newest_file_name);

		if (!file_exists($target)) {
			return null;
		}

		$payload = json_decode(file_get_contents($target), true);

		list($mixed_count, $nonmixed_count) = $payload['coverage'];

		if (!$mixed_count && $nonmixed_count) {
			return '100';
		}

		return number_format(100 * $nonmixed_count / ($mixed_count + $nonmixed_count), 1);
	}

	/**
	 * @return string[]
	 */
	private static function getOrderedFilesInDir(string $repository_data_dir) : array
	{
		$files = scandir($repository_data_dir, SCANDIR_SORT_DESCENDING);
		
		return array_filter(
			$files,
			function (string $filename) : bool {
				return (bool) strpos($filename, '.json');
			}
		);
	}

	public static function getHistory(string $repository) : array
	{
		$repository_data_dir = dirname(__DIR__) . '/database/psalm_master_data/' . $repository;

		if (!file_exists($repository_data_dir)) {
			return [];
		}

		$files = self::getOrderedFilesInDir($repository_data_dir);

		[$owner, $repo_name] = explode("/", $repository);

		$history = [];

		foreach ($files as $file) {
			$target = readlink($repository_data_dir . '/' . $file);

			if (!file_exists($target)) {
				continue;
			}

			$git_commit_hash = explode(".", $file)[0];

			$payload = json_decode(file_get_contents($target), true);

			$github_data = GithubData::getDataForCommit($owner, $repo_name, $git_commit_hash);

			$date = $github_data['commit']['commiter']['date'];

			list($mixed_count, $nonmixed_count) = $payload['coverage'];

			if (!$mixed_count && $nonmixed_count) {
				$c = 100;
			} else {
				$c = 100 * $nonmixed_count / ($mixed_count + $nonmixed_count);
			}

			$history[$date] = $c;
		}

		return $history;
	}

	/** @return string[] */
	public static function getGithubRepositories() : array
	{
		$repositories = [];

		$dir = dirname(__DIR__) . '/database/psalm_master_data/';

		$owners = scandir($dir);

		foreach ($owners as $file) {
			if ($file[0] === '.') {
				continue;
			}

			$owner_dir = $dir . DIRECTORY_SEPARATOR . $file;

			if (is_dir($owner_dir)) {
				$owner_repos = scandir($owner_dir);

				foreach ($owner_repos as $repo_name) {
					if ($repo_name[0] === '.') {
						continue;
					}

					if (is_dir($owner_dir . DIRECTORY_SEPARATOR . $repo_name)) {
						$repositories[] = $file . '/' . $repo_name;
					}
				}
			}
		}
		
		return $repositories;
	}
}