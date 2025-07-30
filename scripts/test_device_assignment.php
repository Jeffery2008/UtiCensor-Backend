<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Utils\Database;
use UtiCensor\Models\Device;
use UtiCensor\Models\RouterZone;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "=== 设备自动分配测试脚本 ===\n\n";

try {
    $db = Database::getInstance();
    $deviceModel = new Device();
    $routerZoneModel = new RouterZone();
    
    // 确保默认区域存在
    $defaultZone = $routerZoneModel->findByIdentifier('default');
    if (!$defaultZone) {
        echo "创建默认路由器区域...\n";
        $zoneData = [
            'zone_name' => '默认区域',
            'router_identifier' => 'default',
            'router_name' => '默认路由器',
            'description' => '系统默认路由器区域',
            'is_active' => 1,
            'created_by' => 1
        ];
        $defaultZoneId = $routerZoneModel->create($zoneData);
        echo "默认区域已创建，ID: $defaultZoneId\n";
    } else {
        $defaultZoneId = $defaultZone['id'];
        echo "默认区域已存在，ID: $defaultZoneId\n";
    }
    
    // 创建测试路由器区域
    $testRouterId = 'router_test_001';
    $testZone = $routerZoneModel->findByIdentifier($testRouterId);
    if (!$testZone) {
        echo "创建测试路由器区域...\n";
        $zoneData = [
            'zone_name' => '测试路由器区域',
            'router_identifier' => $testRouterId,
            'router_name' => '测试路由器',
            'description' => '用于测试的路由器区域',
            'is_active' => 1,
            'created_by' => 1
        ];
        $testZoneId = $routerZoneModel->create($zoneData);
        echo "测试路由器区域已创建，ID: $testZoneId\n";
    } else {
        $testZoneId = $testZone['id'];
        echo "测试路由器区域已存在，ID: $testZoneId\n";
    }
    
    echo "\n=== 测试设备自动分配 ===\n";
    
    // 测试1: 新设备分配到指定路由器区域
    $testMac1 = 'aa:bb:cc:dd:ee:01';
    echo "\n测试1: 新设备分配到测试路由器区域\n";
    echo "MAC地址: $testMac1\n";
    echo "目标路由器区域ID: $testZoneId\n";
    
    $deviceId1 = $deviceModel->autoDetectFromMac($testMac1, '测试设备1', $testZoneId);
    echo "结果: 设备ID = $deviceId1\n";
    
    // 验证分配结果
    $device1 = $deviceModel->findById($deviceId1);
    if ($device1) {
        echo "验证: 设备 '{$device1['device_name']}' 分配到路由器区域ID: {$device1['router_zone_id']}\n";
        if ($device1['router_zone_id'] == $testZoneId) {
            echo "✅ 测试1成功: 设备正确分配到指定路由器区域\n";
        } else {
            echo "❌ 测试1失败: 设备未正确分配\n";
        }
    }
    
    // 测试2: 新设备没有指定路由器区域，应该分配到默认区域
    $testMac2 = 'aa:bb:cc:dd:ee:02';
    echo "\n测试2: 新设备分配到默认路由器区域\n";
    echo "MAC地址: $testMac2\n";
    echo "目标路由器区域ID: $defaultZoneId (默认)\n";
    
    $deviceId2 = $deviceModel->autoDetectFromMac($testMac2, '测试设备2', null);
    echo "结果: 设备ID = $deviceId2\n";
    
    // 验证分配结果
    $device2 = $deviceModel->findById($deviceId2);
    if ($device2) {
        echo "验证: 设备 '{$device2['device_name']}' 分配到路由器区域ID: {$device2['router_zone_id']}\n";
        if ($device2['router_zone_id'] == $defaultZoneId) {
            echo "✅ 测试2成功: 设备正确分配到默认路由器区域\n";
        } else {
            echo "❌ 测试2失败: 设备未正确分配到默认区域\n";
        }
    }
    
    // 测试3: 已存在设备重新分配区域
    $testMac3 = 'aa:bb:cc:dd:ee:03';
    echo "\n测试3: 已存在设备重新分配区域\n";
    echo "MAC地址: $testMac3\n";
    
    // 先创建一个没有区域分配的设备
    $deviceData = [
        'device_name' => '测试设备3',
        'device_identifier' => $testMac3,
        'mac_address' => $testMac3,
        'device_type' => 'Test',
        'description' => '测试设备3',
        'is_active' => 1,
        'router_zone_id' => null
    ];
    $existingDeviceId = $deviceModel->create($deviceData);
    echo "创建了没有区域分配的设备，ID: $existingDeviceId\n";
    
    // 现在尝试重新分配
    $deviceId3 = $deviceModel->autoDetectFromMac($testMac3, null, $testZoneId);
    echo "重新分配结果: 设备ID = $deviceId3\n";
    
    // 验证分配结果
    $device3 = $deviceModel->findById($deviceId3);
    if ($device3) {
        echo "验证: 设备 '{$device3['device_name']}' 分配到路由器区域ID: {$device3['router_zone_id']}\n";
        if ($device3['router_zone_id'] == $testZoneId) {
            echo "✅ 测试3成功: 已存在设备正确重新分配\n";
        } else {
            echo "❌ 测试3失败: 已存在设备未正确重新分配\n";
        }
    }
    
    echo "\n=== 测试完成 ===\n";
    echo "所有测试设备已创建，可以手动清理或保留用于进一步测试\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
} 