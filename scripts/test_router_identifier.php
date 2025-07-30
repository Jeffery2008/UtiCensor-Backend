<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Services\NetifyIngestService;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "=== 路由器标识符测试脚本 ===\n\n";

// 测试IP地址
$testIps = [
    '192.168.1.1',
    '192.168.2.1', 
    '10.0.0.1',
    '154.37.213.151', // 实际服务器IP
    '127.0.0.1'
];

$service = new NetifyIngestService();

echo "测试路由器标识符映射：\n";
echo str_repeat('-', 50) . "\n";

foreach ($testIps as $ip) {
    // 使用反射来访问私有方法
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getRouterIdentifierFromConnection');
    $method->setAccessible(true);
    
    $identifier = $method->invoke($service, $ip, '0.0.0.0');
    
    echo sprintf("%-15s -> %s\n", $ip, $identifier ?? 'null');
}

echo "\n=== 配置信息 ===\n";
$config = require __DIR__ . '/../config/app.php';

echo "路由器标识符映射：\n";
if (empty($config['router_identifier_mapping'])) {
    echo "  无配置\n";
} else {
    foreach ($config['router_identifier_mapping'] as $ip => $identifier) {
        echo "  $ip -> $identifier\n";
    }
}

echo "\n路由器映射：\n";
if (empty($config['router_mapping'])) {
    echo "  无配置\n";
} else {
    foreach ($config['router_mapping'] as $ip => $identifier) {
        echo "  $ip -> $identifier\n";
    }
}

echo "\nNetify设置：\n";
echo "  允许未知设备: " . ($config['netify']['allow_unknown_devices'] ? '是' : '否') . "\n";
echo "  允许未知区域: " . ($config['netify']['allow_unknown_zones'] ? '是' : '否') . "\n";
echo "  自动创建设备: " . ($config['netify']['auto_create_devices'] ? '是' : '否') . "\n";
echo "  自动创建区域: " . ($config['netify']['auto_create_zones'] ? '是' : '否') . "\n";

echo "\n=== 建议 ===\n";
echo "1. 根据实际路由器IP地址配置 router_identifier_mapping\n";
echo "2. 确保路由器脚本发送正确的标识符\n";
echo "3. 检查路由器脚本中的 get_router_identifier() 函数\n";
echo "4. 确保默认区域存在\n"; 