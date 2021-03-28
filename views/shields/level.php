<?php

require '../../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
	throw new \UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
	throw new \UnexpectedValueException('Unexpected values in repository name');
}

$level = Psalm\Shepherd\Api::getLevel($repository);

header('Content-type: image/svg+xml;charset=utf-8');
header('Cache-control: max-age=0, no-cache');

if (!$level) {
	echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="96" height="20"><linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="r"><rect width="96" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#r)"><rect width="43" height="20" fill="#555"/><rect x="43" width="53" height="20" fill="#999"/><rect width="96" height="20" fill="url(#s)"/></g><g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="optimizeLegibility" font-size="110"><text aria-hidden="true" x="225" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="330">Psalm</text><text x="225" y="140" transform="scale(.1)" fill="#fff" textLength="330">Psalm</text><text aria-hidden="true" x="685" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="430">enabled</text><text x="685" y="140" transform="scale(.1)" fill="#fff" textLength="430">enabled</text></g></svg>
SVG;
	exit;
}

if ($level === 1) {
	$color = '#4c1';
} elseif ($level === 2 || $level === 3) {
	$color = '#97ca00';
} elseif ($level === 4) {
	$color = '#aeaf12';
} elseif ($level === 5) {
	$color = '#dfb317';
} elseif ($level === 6) {
	$color = '#fe7d37';
} else {
	$color = '#e05d44';
}

echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="90" height="20"><linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient><clipPath id="r"><rect width="90" height="20" rx="3" fill="#fff"/></clipPath><g clip-path="url(#r)"><rect width="73" height="20" fill="#555"/><rect x="73" width="17" height="20" fill="{$color}"/><rect width="90" height="20" fill="url(#s)"/></g><g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" text-rendering="optimizeLegibility" font-size="110"><text aria-hidden="true" x="375" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="630">Psalm level</text><text x="375" y="140" transform="scale(.1)" fill="#fff" textLength="630">Psalm level</text><text aria-hidden="true" x="805" y="150" fill="#010101" fill-opacity=".3" transform="scale(.1)" textLength="70">{$level}</text><text x="805" y="140" transform="scale(.1)" fill="#fff" textLength="70">{$level}</text></g></svg>
SVG;
