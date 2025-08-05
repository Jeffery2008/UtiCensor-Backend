<?php
/**
 * Web测试页面 - 验证日志不会输出到Web响应中
 */

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Utils\Logger;

// 设置响应头
header('Content-Type: application/json');

// 记录一些测试日志
Logger::info("Web测试 - 信息日志", 'test');
Logger::warning("Web测试 - 警告日志", 'test');
Logger::error("Web测试 - 错误日志", 'test');

// 返回测试结果
$response = [
    'status' => 'success',
    'message' => '日志测试完成',
    'sapi_name' => php_sapi_name(),
    'is_cli' => php_sapi_name() === 'cli',
    'timestamp' => date('c'),
    'note' => '如果修复成功，这个响应中不应该包含日志输出'
];

echo json_encode($response, JSON_PRETTY_PRINT); 