#! /usr/bin/env php

<?php
require 'vendor/autoload.php';

use Acme\CheckCommand;
use Symfony\Component\Console\Application;

define('APPLICATION_NAME', 'Google Sheets API PHP Quickstart');
define('CREDENTIALS_PATH', __DIR__ . '/config/credentials/sheets.googleapis.com-php-quickstart.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/config/client_secret.json');

define('SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS_READONLY)
));

$app = new Application("Lunch repport", '1.0');
$app->add(new CheckCommand(require __DIR__ . '/config/lunch.php'));
$app->run();        