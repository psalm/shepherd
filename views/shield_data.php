<?php

require '../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
	throw new \UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
	throw new \UnexpectedValueException('Unexpected values in repository name');
}

$pct = Psalm\Shepherd\Api::getTypeCoverage($repository);

header('Cache-control: max-age=0, no-cache');

$data = [
	'schemaVersion' => 1,
	'label' => 'type-coverage',
];

if (!$pct) {
	$data += [
		'message' => 'unknown',
		'color' => '#aaa'
	];

	echo json_encode($data);
	exit;
}

if ($pct > 95) {
	$color = '#4c1';
} elseif ($pct > 90) {
	$color = '#97ca00';
} elseif ($pct > 85) {
	$color = '#aeaf12';
} elseif ($pct > 80) {
	$color = '#dfb317';
} elseif ($pct > 75) {
	$color = '#fe7d37';
} else {
	$color = '#e05d44';
}

$pct = $pct . '%';

$data += [
	'message' => $pct,
	'color' => $color,
];

echo json_encode($data);