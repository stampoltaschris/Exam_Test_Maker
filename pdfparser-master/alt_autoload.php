<?php

/**
 * Χειροκίνητος Autoloader για τη βιβλιοθήκη Smalot PdfParser
 */
spl_autoload_register(function ($class) {
    $prefix = 'Smalot\\PdfParser\\';
    $base_dir = __DIR__ . '/src/';

    if (strpos($class, $prefix) === 0) {
        $file = $base_dir . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});