<?php

namespace marcocesarato\amwscan;

include 'Argument.php';
include 'Argv.php';
include 'Console.php';
include 'CSV.php';
include 'Definitions.php';
include 'Flag.php';
include 'Deobfuscator.php';
include 'Application.php';

$isCLI = (php_sapi_name() === 'cli');
if (!$isCLI) {
    die('This file must run from a console session.');
}

// Settings
ini_set('memory_limit', '1G');
ini_set('xdebug.max_nesting_level', 500);
ob_implicit_flush(false);
set_time_limit(-1);

// Errors
error_reporting(0);
ini_set('display_errors', 0);

$app = new Application();
$app->run();
