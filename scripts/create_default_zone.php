<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Utils\Database;
use UtiCensor\Models\RouterZone;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

try {
    $db = Database::getInstance();
    $routerZoneModel = new RouterZone();
    
    // 检查默认区域是否存在
    $defaultZone = $routerZoneModel->findByIdentifier('default');
    
    if (!$defaultZone) {
        // 创建默认区域
        $zoneData = [
            'zone_name' => '默认区域',
            'router_identifier' => 'default',
            'router_name' => '默认路由器',
            'description' => '系统默认路由器区域，用于处理未映射的设备',
            'is_active' => 1,
            'created_by' => 1 // admin用户
        ];
        
        $zoneId = $routerZoneModel->create($zoneData);
        echo "Created default router zone with ID: $zoneId\n";
    } else {
        echo "Default router zone already exists with ID: {$defaultZone['id']}\n";
    }
    
    // 将没有区域的设备分配到默认区域
    $unassignedDevices = $db->fetchAll('
        SELECT id, device_name, mac_address 
        FROM devices 
        WHERE router_zone_id IS NULL OR router_zone_id = 0
    ');
    
    if (!empty($unassignedDevices)) {
        $defaultZoneId = $defaultZone ? $defaultZone['id'] : $zoneId;
        
        foreach ($unassignedDevices as $device) {
            $db->update('devices', ['router_zone_id' => $defaultZoneId], ['id' => $device['id']]);
            echo "Assigned device '{$device['device_name']}' to default zone\n";
        }
        
        echo "Assigned " . count($unassignedDevices) . " devices to default zone\n";
    } else {
        echo "No unassigned devices found\n";
    }
    
    echo "Script completed successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 