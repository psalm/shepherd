<?php

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
	throw new \UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
	throw new \UnexpectedValueException('Unexpected values in repository name');
}

$repository_data_dir = __DIR__ . '/database/psalm_master_data/' . $repository;

$pct = '?? ';

var_dump($repository_data_dir, file_exists($repository_data_dir));
exit;

if (file_exists($repository_data_dir)) {
	$files = scandir($repository_data_dir, SCANDIR_SORT_DESCENDING);
	$newest_file_name = array_filter(
		$files,
		function (string $filename) {
			return strpos($filename, '.json');
		}
	)[0];

	$payload = json_decode(file_get_contents($newest_file_name), true);

	list($mixed_count, $nonmixed_count) = $payload['coverage'];

	if (!$mixed_count && $nonmixed_count) {
		$pct = '100';
	} else {
		$pct = number_format(100 * $nonmixed_count / ($mixed_count + $nonmixed_count), 1);
	}
}

header('Content-type: image/svg+xml;charset=utf-8');

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="136" height="20"><linearGradient id="b" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="a"><rect width="136" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#a)"><path fill="#555" d="M0 0h89v20H0z"/><path fill="#6370b5" d="M89 0h47v20H89z"/><path fill="url(#b)" d="M0 0h136v20H0z"/></g><g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110"> <text x="455" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="790">type-coverage</text><text x="455" y="140" transform="scale(.1)" textLength="790">type-coverage</text><text x="1115" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="370">{$pct}%</text><text x="1115" y="140" transform="scale(.1)" textLength="370">{$pct}%</text></g> </svg>
SVG;
