<?php

require_once __DIR__ . '/../vendor/autoload.php';

// 加载环境变量
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

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
use UtiCensor\Controllers\RouterZoneController;
use UtiCensor\Controllers\RouterMappingController;

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
            
        case $path === '/auth/refresh' && $requestMethod === 'POST':
            (new AuthController())->refresh();
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
            
        case $path === '/devices/stats' && $requestMethod === 'GET':
            (new DeviceController())->getStats();
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
            
        case $path === '/flows/applications' && $requestMethod === 'GET':
            (new NetworkFlowController())->getTopApplications();
            break;
            
        case $path === '/flows/top-applications' && $requestMethod === 'GET':
            (new NetworkFlowController())->getTopApplications();
            break;
            
        case $path === '/flows/top-protocols' && $requestMethod === 'GET':
            (new NetworkFlowController())->getTopProtocols();
            break;
            
        case $path === '/flows/top-hosts' && $requestMethod === 'GET':
            (new NetworkFlowController())->getTopHosts();
            break;
            
        case $path === '/flows/hosts' && $requestMethod === 'GET':
            (new NetworkFlowController())->getTopHosts();
            break;
            
        case $path === '/flows/export' && $requestMethod === 'GET':
            (new NetworkFlowController())->export();
            break;

        // Router Zone routes
        case $path === '/router-zones' && $requestMethod === 'GET':
            (new RouterZoneController())->index();
            break;
            
        case $path === '/router-zones' && $requestMethod === 'POST':
            (new RouterZoneController())->create();
            break;
            
        case preg_match('/^\/router-zones\/(\d+)$/', $path, $matches) && $requestMethod === 'GET':
            $_GET['id'] = $matches[1];
            (new RouterZoneController())->show();
            break;
            
        case preg_match('/^\/router-zones\/(\d+)$/', $path, $matches) && $requestMethod === 'PUT':
            $_GET['id'] = $matches[1];
            (new RouterZoneController())->update();
            break;
            
        case preg_match('/^\/router-zones\/(\d+)$/', $path, $matches) && $requestMethod === 'DELETE':
            $_GET['id'] = $matches[1];
            (new RouterZoneController())->delete();
            break;
            
        case $path === '/router-zones/stats' && $requestMethod === 'GET':
            (new RouterZoneController())->stats();
            break;

        // Router Mapping routes
        case $path === '/router-mapping' && $requestMethod === 'GET':
            (new RouterMappingController())->index();
            break;
            
        case $path === '/router-mapping/config' && $requestMethod === 'GET':
            (new RouterMappingController())->getConfig();
            break;
            
        case $path === '/router-mapping' && $requestMethod === 'PUT':
            (new RouterMappingController())->update();
            break;
            
        case $path === '/router-mapping/add' && $requestMethod === 'POST':
            (new RouterMappingController())->addMapping();
            break;
            
        case $path === '/router-mapping/remove' && $requestMethod === 'DELETE':
            (new RouterMappingController())->removeMapping();
            break;
            
        case $path === '/router-mapping/test' && $requestMethod === 'GET':
            (new RouterMappingController())->testMapping();
            break;
            
        case $path === '/router-mapping/stats' && $requestMethod === 'GET':
            (new RouterMappingController())->getStats();
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

        // Debug headers
        case $path === '/debug-headers' && $requestMethod === 'GET':
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'headers' => getallheaders(),
                'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'not set',
                'server_vars' => [
                    'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'not set',
                    'HTTP_AUTHORIZATION_LOWER' => $_SERVER['HTTP_AUTHORIZATION_LOWER'] ?? 'not set'
                ]
            ]);
            break;

        // Debug JWT
        case $path === '/debug-jwt' && $requestMethod === 'GET':
            require_once __DIR__ . '/../vendor/autoload.php';
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            
            $result = [
                'headers' => $headers,
                'auth_header' => $authHeader,
                'jwt_test' => null
            ];
            
            if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = $matches[1];
                $result['token'] = substr($token, 0, 50) . '...';
                
                try {
                    $payload = \UtiCensor\Utils\JWT::decode($token);
                    $result['jwt_test'] = $payload;
                } catch (Exception $e) {
                    $result['jwt_error'] = $e->getMessage();
                }
            }
            
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode($result);
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

