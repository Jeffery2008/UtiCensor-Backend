<?php

require_once __DIR__ . '/vendor/autoload.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'UtiCensor\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use UtiCensor\Services\NetifyIngestService;

// Set timezone
date_default_timezone_set('Asia/Shanghai');

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

try {
    $service = new NetifyIngestService();
    $service->startListener();
} catch (Exception $e) {
    fwrite(STDERR, date('c') . " Fatal error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

