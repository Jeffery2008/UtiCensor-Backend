<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Utils\Database;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "=== 清理测试数据脚本 ===\n\n";

try {
    $db = Database::getInstance();
    
    // 清理测试设备
    $testMacs = [
        'aa:bb:cc:dd:ee:01',
        'aa:bb:cc:dd:ee:02', 
        'aa:bb:cc:dd:ee:03'
    ];
    
    $deletedDevices = 0;
    foreach ($testMacs as $mac) {
        $result = $db->delete('devices', ['mac_address' => $mac]);
        if ($result > 0) {
            echo "已删除测试设备: $mac\n";
            $deletedDevices++;
        }
    }
    
    // 清理测试路由器区域
    $testRouterId = 'router_test_001';
    $result = $db->delete('router_zones', ['router_identifier' => $testRouterId]);
    if ($result > 0) {
        echo "已删除测试路由器区域: $testRouterId\n";
    }
    
    echo "\n清理完成:\n";
    echo "- 删除了 $deletedDevices 个测试设备\n";
    echo "- 删除了测试路由器区域\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
} 