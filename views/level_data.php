<?php

require '../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
    throw new UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
    throw new UnexpectedValueException('Unexpected values in repository name');
}

$level = Psalm\Shepherd\Api::getLevel($repository);

header('Cache-control: max-age=0, no-cache');

$data = [
    'schemaVersion' => 1,
    'label' => 'psalm-level',
];

if (!$level) {
    $data += [
        'message' => 'unknown',
        'color' => '#aaa'
    ];

    echo json_encode($data);
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

$data += [
    'message' => (string)$level,
    'color' => $color,
];

echo json_encode($data);
