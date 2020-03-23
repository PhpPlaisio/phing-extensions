<?php
error_reporting(E_ALL);
date_default_timezone_set('Europe/Amsterdam');

require __DIR__.'/../vendor/autoload.php';

defined('PHING_TEST_BASE') || define('PHING_TEST_BASE', dirname(__FILE__));

require_once(dirname(__FILE__) . '/phing/BuildFileTest.php');

Phing::startup();
