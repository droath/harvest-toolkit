<?php

declare(strict_types=1);

use Droath\HarvestToolkit\HarvestToolkit;

define('APP_ROOT', dirname(__DIR__));

// Define possible paths to search for the composer autoloader.
$autoLoaders = [
    '/../../autoload.php',
    '/../../vendor/autoload.php',
    '/vendor/autoload.php',
];
$autoloadPath = false;

foreach ($autoLoaders as $path) {
    if (file_exists(APP_ROOT . $path)) {
        $autoloadPath = APP_ROOT . $path;
        break;
    }
}

if (!$autoloadPath) {
    die("Could not find autoloader. Run 'composer install'.");
}
$classLoader = require "$autoloadPath";

$statusCode = (new HarvestToolkit())->execute();

exit($statusCode);
