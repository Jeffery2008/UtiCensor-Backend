<?php
/**
 * 简单日志测试 - 不依赖数据库
 */

// 模拟配置
$config = [
    'netify' => [
        'log_level' => 'info',
        'log_console_output' => false
    ]
];

// 模拟 Logger 类的核心逻辑
function shouldOutputToConsole() {
    // 在CLI模式下总是输出
    if (php_sapi_name() === 'cli') {
        return true;
    }
    
    // 在Web模式下，根据配置决定是否输出
    global $config;
    return $config['netify']['log_console_output'] ?? false;
}

function testLog($level, $message) {
    if (shouldOutputToConsole()) {
        echo date('c') . " [{$level}] " . $message . "\n";
    }
    echo "✅ 日志已记录到数据库 (模拟)\n";
}

echo "=== 日志输出测试 ===\n";
echo "当前运行模式: " . php_sapi_name() . "\n";
echo "是否为CLI模式: " . (php_sapi_name() === 'cli' ? '是' : '否') . "\n";
echo "控制台输出配置: " . ($config['netify']['log_console_output'] ? '开启' : '关闭') . "\n\n";

echo "测试日志输出:\n";
testLog('INFO', '这是信息日志');
testLog('WARNING', '这是警告日志');
testLog('ERROR', '这是错误日志');

echo "\n=== 测试完成 ===\n";
echo "在CLI模式下，你应该看到日志输出。\n";
echo "在Web模式下，你应该只看到数据库记录消息。\n"; 