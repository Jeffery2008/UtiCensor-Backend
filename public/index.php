<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Shanghai');

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'UtiCensor\\';
    $base_dir = __DIR__ . '/../src/';
    
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

use UtiCensor\Controllers\AuthController;
use UtiCensor\Controllers\DeviceController;
use UtiCensor\Controllers\FilterController;
use UtiCensor\Controllers\NetworkFlowController;

// Simple router
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if exists
$basePath = '/api';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Route handling
try {
    switch (true) {
        // Auth routes
        case $path === '/auth/login' && $requestMethod === 'POST':
            (new AuthController())->login();
            break;
            
        case $path === '/auth/register' && $requestMethod === 'POST':
            (new AuthController())->register();
            break;
            
        case $path === '/auth/me' && $requestMethod === 'GET':
            (new AuthController())->me();
            break;
            
        case $path === '/auth/change-password' && $requestMethod === 'POST':
            (new AuthController())->changePassword();
            break;
            
        case $path === '/auth/logout' && $requestMethod === 'POST':
            (new AuthController())->logout();
            break;

        // Device routes
        case $path === '/devices' && $requestMethod === 'GET':
            (new DeviceController())->index();
            break;
            
        case $path === '/devices' && $requestMethod === 'POST':
            (new DeviceController())->create();
            break;
            
        case preg_match('/^\/devices\/(\d+)$/', $path, $matches) && $requestMethod === 'GET':
            $_GET['id'] = $matches[1];
            (new DeviceController())->show();
            break;
            
        case preg_match('/^\/devices\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT':
            $_GET['id'] = $matches[1];
            (new DeviceController())->update();
            break;
            
        case preg_match('/^\/devices\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE':
            $_GET['id'] = $matches[1];
            (new DeviceController())->delete();
            break;
            
        case $path === '/devices/types' && $requestMethod === 'GET':
            (new DeviceController())->getTypes();
            break;
            
        case preg_match('/^\/devices\/(\d+)\/interfaces$/', $path, $matches) && $requestMethod === 'POST':
            $_GET['device_id'] = $matches[1];
            (new DeviceController())->addInterface();
            break;

        // Filter routes
        case $path === '/filters' && $requestMethod === 'GET':
            (new FilterController())->index();
            break;
            
        case $path === '/filters' && $requestMethod === 'POST':
            (new FilterController())->create();
            break;
            
        case preg_match('/^\/filters\/(\d+)$/', $path, $matches) && $requestMethod === 'GET':
            $_GET['id'] = $matches[1];
            (new FilterController())->show();
            break;
            
        case preg_match('/^\/filters\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT':
            $_GET['id'] = $matches[1];
            (new FilterController())->update();
            break;
            
        case preg_match('/^\/filters\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE':
            $_GET['id'] = $matches[1];
            (new FilterController())->delete();
            break;
            
        case $path === '/filters/fields' && $requestMethod === 'GET':
            (new FilterController())->getFields();
            break;
            
        case $path === '/filters/operators' && $requestMethod === 'GET':
            (new FilterController())->getOperators();
            break;
            
        case preg_match('/^\/filters\/(\d+)\/test$/', $path, $matches) && $requestMethod === 'GET':
            $_GET['id'] = $matches[1];
            (new FilterController())->testFilter();
            break;

        // Network flow routes
        case $path === '/flows' && $requestMethod === 'GET':
            (new NetworkFlowController())->index();
            break;
            
        case preg_match('/^\/flows\/(\d+)$/', $path, $matches) && $requestMethod === 'GET':
            $_GET['id'] = $matches[1];
            (new NetworkFlowController())->show();
            break;
            
        case $path === '/flows/filter' && $requestMethod === 'GET':
            (new NetworkFlowController())->getByFilter();
            break;
            
        case $path === '/flows/stats' && $requestMethod === 'GET':
            (new NetworkFlowController())->getStats();
            break;
            
        case $path === '/flows/stats/hourly' && $requestMethod === 'GET':
            (new NetworkFlowController())->getHourlyStats();
            break;
            
        case $path === '/flows/applications' && $requestMethod === 'GET':
            (new NetworkFlowController())->getApplications();
            break;
            
        case $path === '/flows/protocols' && $requestMethod === 'GET':
            (new NetworkFlowController())->getProtocols();
            break;
            
        case $path === '/flows/export' && $requestMethod === 'GET':
            (new NetworkFlowController())->export();
            break;

        // Health check
        case $path === '/health' && $requestMethod === 'GET':
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => '2.0.0'
            ]);
            break;

        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Route not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = ['error' => 'Internal server error'];
    
    // Include error details in debug mode
    $config = require __DIR__ . '/../config/app.php';
    if ($config['debug']) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    echo json_encode($response);
}

