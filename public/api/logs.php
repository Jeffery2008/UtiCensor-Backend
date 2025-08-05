<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use UtiCensor\Controllers\LogController;
use UtiCensor\Utils\Logger;

// 设置错误处理
set_error_handler(function($severity, $message, $file, $line) {
    Logger::systemError($message, null, [
        'code' => $severity,
        'file' => $file,
        'line' => $line
    ]);
});

set_exception_handler(function($exception) {
    Logger::exception($exception, 'uncaught_exception');
});

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $controller = new LogController();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // 获取最后一个路径段作为操作
    $action = end($pathParts);
    
    // 记录请求开始
    $startTime = microtime(true);
    
    // 路由处理
    switch ($action) {
        case 'stats':
            if ($method === 'GET') {
                $controller->stats();
            } else {
                http_response_code(405);
                echo json_encode(['error' => '方法不允许']);
            }
            break;
            
        case 'cleanup':
            if ($method === 'POST') {
                $controller->cleanup();
            } else {
                http_response_code(405);
                echo json_encode(['error' => '方法不允许']);
            }
            break;
            
        case 'export':
            if ($method === 'GET') {
                $controller->export();
            } else {
                http_response_code(405);
                echo json_encode(['error' => '方法不允许']);
            }
            break;
            
        default:
            // 检查是否是获取单个日志
            if (is_numeric($action) && $method === 'GET') {
                $controller->show((int)$action);
            } else if ($method === 'GET') {
                $controller->index();
            } else {
                http_response_code(404);
                echo json_encode(['error' => '接口不存在']);
            }
            break;
    }
    
    // 记录请求完成
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    Logger::apiRequestLog(
        $method,
        $_SERVER['REQUEST_URI'],
        $method === 'GET' ? $_GET : $_POST,
        null,
        http_response_code(),
        $executionTime
    );
    
} catch (\Throwable $e) {
    Logger::exception($e, 'api_error', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI']
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => '服务器内部错误',
        'message' => $e->getMessage()
    ]);
} 