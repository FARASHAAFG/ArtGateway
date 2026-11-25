<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

putenv('APP_ROOT_DIR=');
$_ENV['APP_ROOT_DIR'] = '';
$_SERVER['APP_ROOT_DIR'] = '';

require $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/config/constants.php';
