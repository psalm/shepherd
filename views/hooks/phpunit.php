<?php

require '../../vendor/autoload.php';

[$git_commit_hash, $payload] = Psalm\Shepherd\Hook::readPayload();

\Psalm\Shepherd\PhpunitData::handleFailurePayload($git_commit_hash, $payload);
