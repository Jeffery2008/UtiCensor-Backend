<?php
/**
 * 测试日志输出是否只在CLI模式下输出到控制台
 */

require_once __DIR__ . '/vendor/autoload.php';

use UtiCensor\Utils\Logger;

echo "测试日志输出模式...\n\n";

// 测试 1: 检查当前运行模式
echo "当前运行模式: " . php_sapi_name() . "\n";
echo "是否为CLI模式: " . (php_sapi_name() === 'cli' ? '是' : '否') . "\n\n";

// 测试 2: 测试各种日志级别
echo "测试各种日志级别:\n";

Logger::debug("这是调试信息", 'test');
Logger::info("这是信息日志", 'test');
Logger::warning("这是警告信息", 'test');
Logger::error("这是错误信息", 'test');

echo "\n日志测试完成！\n";
echo "如果这是CLI模式，你应该看到上面的日志输出。\n";
echo "如果这是Web模式，你应该只看到这条消息，没有日志输出。\n"; 