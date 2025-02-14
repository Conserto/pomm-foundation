<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) { throw new ErrorException($errstr, 0, $errno, $errfile, $errline); });
$loader = require __DIR__ . '/vendor/autoload.php';
$file = __DIR__.'/sources/tests/config.php';

if (file_exists($file)) {
    // custom configuration
    require $file;
} else {
    // we are using travis configuration by default
    require __DIR__.'/sources/tests/config.github.php';
}

