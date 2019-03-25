<?php

require '../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
	throw new \UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
	throw new \UnexpectedValueException('Unexpected values in repository name');
}

$pct = Psalm\Spirit\Api::getTypeCoverage($repository);

header('Content-type: image/svg+xml;charset=utf-8');
header('Cache-control: max-age=0, no-cache');

if (!$pct) {
	echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="150" height="20"><linearGradient id="b" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="a"><rect width="150" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#a)"><path fill="#555" d="M0 0h89v20H0z"/><path fill="#aaa" d="M89 0h61v20H89z"/><path fill="url(#b)" d="M0 0h150v20H0z"/></g><g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110"> <text x="455" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="790">type-coverage</text><text x="455" y="140" transform="scale(.1)" textLength="790">type-coverage</text><text x="1185" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="510">unknown</text><text x="1185" y="140" transform="scale(.1)" textLength="510">unknown</text></g> </svg>
SVG;
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

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="136" height="20"><linearGradient id="b" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="a"><rect width="136" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#a)"><path fill="#555" d="M0 0h89v20H0z"/><path fill="{$color}" d="M89 0h47v20H89z"/><path fill="url(#b)" d="M0 0h136v20H0z"/></g><g fill="#fff" text-anchor="middle" font-family="DejaVu Sans,Verdana,Geneva,sans-serif" font-size="110"> <text x="455" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="790">type-coverage</text><text x="455" y="140" transform="scale(.1)" textLength="790">type-coverage</text><text x="1115" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="370">{$pct}</text><text x="1115" y="140" transform="scale(.1)" textLength="370">{$pct}</text></g> </svg>
SVG;
