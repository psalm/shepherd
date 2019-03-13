<?php

$payload = json_decode($_POST['payload']);

error_log(var_export($payload, true));
