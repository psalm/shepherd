<?php

namespace Psalm\Spirit;

class Api
{
	public static function getTypeCoverage(string $repository) : ?string
	{
		$repository_data_dir = dirname(__DIR__) . '/database/psalm_master_data/' . $repository;

		if (!file_exists($repository_data_dir)) {
			return null;
		}

		$files = scandir($repository_data_dir, SCANDIR_SORT_DESCENDING);
		$newest_file_name = array_filter(
			$files,
			function (string $filename) : bool {
				return (bool) strpos($filename, '.json');
			}
		)[0];

		$target = readlink($repository_data_dir . '/' . $newest_file_name);

		if (!file_exists($target)) {
			return null;
		}

		$payload = json_decode(file_get_contents($target), true);

		list($mixed_count, $nonmixed_count) = $payload['coverage'];

		if (!$mixed_count && $nonmixed_count) {
			return '100%';
		}

		return number_format(100 * $nonmixed_count / ($mixed_count + $nonmixed_count), 1) . '%';
	}
}