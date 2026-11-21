<?php

require '../../vendor/autoload.php';

[$git_commit_hash, $payload] = Psalm\Shepherd\Hook::readPayload();

if (!is_array($payload['tests'] ?? null)) {
    Psalm\Shepherd\Hook::reject(400, 'Payload has no failed tests');
}

\Psalm\Shepherd\PhpunitData::handleFailurePayload($git_commit_hash, $payload);
