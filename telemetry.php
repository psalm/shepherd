<?php

$payload = json_decode(file_get_contents('php://input'), true);

error_log(var_export($payload, true));
