<?php

$payload = json_decode($_POST['payload'], true);

error_log(var_export($payload, true));
