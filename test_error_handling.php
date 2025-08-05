<?php
/**
 * 测试错误处理是否正常工作
 */

require_once __DIR__ . '/vendor/autoload.php';

use UtiCensor\Utils\Logger;

echo "测试错误处理...\n\n";

// 测试 1: 测试 Exception 处理
echo "测试 1: Exception 处理\n";
try {
    throw new Exception("这是一个测试异常");
} catch (\Throwable $e) {
    Logger::exception($e, 'test_exception');
    echo "✅ Exception 处理正常\n";
}

// 测试 2: 测试 Error 处理
echo "\n测试 2: Error 处理\n";
try {
    // 故意触发一个 Error
    $undefined = $undefined_variable;
} catch (\Throwable $e) {
    Logger::exception($e, 'test_error');
    echo "✅ Error 处理正常\n";
}

// 测试 3: 测试 TypeError 处理
echo "\n测试 3: TypeError 处理\n";
try {
    // 故意触发一个 TypeError
    $result = array_merge("not_an_array", [1, 2, 3]);
} catch (\Throwable $e) {
    Logger::exception($e, 'test_type_error');
    echo "✅ TypeError 处理正常\n";
}

// 测试 4: 测试 ParseError 处理（如果可能的话）
echo "\n测试 4: 测试 Logger 方法\n";
try {
    Logger::info("测试信息", 'test');
    Logger::warning("测试警告", 'test');
    Logger::error("测试错误", 'test');
    echo "✅ Logger 方法调用正常\n";
} catch (\Throwable $e) {
    echo "❌ Logger 方法调用失败: " . $e->getMessage() . "\n";
}

echo "\n错误处理测试完成！\n"; 