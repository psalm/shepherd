<?php

require '../../vendor/autoload.php';

[$git_commit_hash, $payload] = Psalm\Shepherd\Hook::readPayload();

$coverage = $payload['coverage'] ?? null;

if (!is_array($payload['issues'] ?? null)
    || !is_array($coverage)
    || !is_int($coverage[0] ?? null)
    || !is_int($coverage[1] ?? null)
) {
    Psalm\Shepherd\Hook::reject(400, 'Payload has no Psalm issues or type coverage');
}

if (!is_int($payload['level'] ?? 0)) {
    Psalm\Shepherd\Hook::reject(400, 'Psalm level is not an integer');
}

Psalm\Shepherd\PsalmData::handlePayload($git_commit_hash, $payload);
