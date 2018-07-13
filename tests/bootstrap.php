<?php

ini_set('error_reporting', E_ALL | E_STRICT);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

$autoloader = dirname(__FILE__) . '/../vendor/autoload.php';

$loader = include $autoloader;
$loader->add('', __DIR__);
