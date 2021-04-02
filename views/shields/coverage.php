<?php

require '../../vendor/autoload.php';

$label = $_GET['label'] ?? 'type-coverage';
$repository = $_GET['repository'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
    exit('Repository format not recognised');
}

if (strpos($repository, '..') !== false) {
    exit('Unexpected values in repository name');
}

$pct = Psalm\Shepherd\Api::getTypeCoverage($repository);

header('Content-type: image/svg+xml;charset=utf-8');
header('Cache-control: max-age=0, no-cache');

if (!$pct) {
    $pct = 'unknown';
    $color = '#aaa';
} elseif ($pct > 95) {
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

if ($pct !== 'unknown') {
    $pct = $pct . '%';
}


$cachedFile = __DIR__ . '/cache/' . md5($pct . $label) . '.svg';

if (!file_exists($cachedFile)) {
    file_put_contents(
        $cachedFile,
        file_get_contents(sprintf(
            'https://img.shields.io/badge/%s-%s-%s',
            $label,
            urlencode($pct),
            urlencode($color)
        ))
    );
}

readfile($cachedFile);
