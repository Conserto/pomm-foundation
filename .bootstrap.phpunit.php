<?php

require __DIR__ . '/vendor/autoload.php';

$file = __DIR__ . '/sources/tests/config.php';

if (file_exists($file)) {
    require $file;
} else {
    require __DIR__ . '/sources/tests/config.github.php';
}
