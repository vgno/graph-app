<?php

define('BASE_PATH', realpath(__DIR__ . '/..'));
require BASE_PATH . '/vendor/autoload.php';

$config = require BASE_PATH . '/config/config.php';

$app = new Graph\Application($config);

$app->bootstrap()
    ->run();
