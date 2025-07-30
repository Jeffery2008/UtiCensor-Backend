<?php

require_once __DIR__ . '/../vendor/autoload.php';

use UtiCensor\Utils\Database;
use UtiCensor\Models\RouterZone;
use UtiCensor\Models\Device;

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "=== 路由器概览测试脚本 ===\n\n";

try {
    $db = Database::getInstance();
    $routerZoneModel = new RouterZone();
    $deviceModel = new Device();
    
    // 获取所有路由器区域
    $zones = $routerZoneModel->getAll();
    
    echo "路由器区域总数: " . count($zones) . "\n\n";
    
    if (empty($zones)) {
        echo "暂无路由器区域数据\n";
        echo "建议:\n";
        echo "1. 配置路由器映射\n";
        echo "2. 等待路由器连接并发送数据\n";
        echo "3. 系统会自动创建路由器区域\n";
        exit(0);
    }
    
    // 获取设备统计
    $deviceStats = $deviceModel->getDeviceStats();
    
    echo "=== 路由器区域详情 ===\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-20s %-15s %-25s %-10s %-10s %-10s\n", 
           "区域名称", "路由器名称", "标识符", "设备数", "活跃设备", "状态");
    echo str_repeat('-', 80) . "\n";
    
    $totalDevices = 0;
    $totalActiveDevices = 0;
    $activeRouters = 0;
    
    foreach ($zones as $zone) {
        $zoneStats = $deviceStats['router_zones'] ?? [];
        $zoneDeviceStats = null;
        
        // 查找该区域的设备统计
        foreach ($zoneStats as $stat) {
            if ($stat['id'] == $zone['id']) {
                $zoneDeviceStats = $stat;
                break;
            }
        }
        
        $deviceCount = $zoneDeviceStats['device_count'] ?? 0;
        $activeDeviceCount = $zoneDeviceStats['active_device_count'] ?? 0;
        $status = $zone['is_active'] ? ($deviceCount > 0 ? '活跃' : '无设备') : '禁用';
        
        printf("%-20s %-15s %-25s %-10s %-10s %-10s\n",
               substr($zone['zone_name'], 0, 19),
               substr($zone['router_name'] ?? '-', 0, 14),
               substr($zone['router_identifier'], 0, 24),
               $deviceCount,
               $activeDeviceCount,
               $status);
        
        $totalDevices += $deviceCount;
        $totalActiveDevices += $activeDeviceCount;
        if ($zone['is_active']) $activeRouters++;
    }
    
    echo str_repeat('-', 80) . "\n";
    printf("%-20s %-15s %-25s %-10s %-10s %-10s\n",
           "总计", "", "", $totalDevices, $totalActiveDevices, "");
    
    echo "\n=== 统计摘要 ===\n";
    echo "路由器总数: " . count($zones) . "\n";
    echo "活跃路由器: " . $activeRouters . "\n";
    echo "禁用路由器: " . (count($zones) - $activeRouters) . "\n";
    echo "总设备数: " . $totalDevices . "\n";
    echo "活跃设备数: " . $totalActiveDevices . "\n";
    echo "设备活跃率: " . ($totalDevices > 0 ? round(($totalActiveDevices / $totalDevices) * 100, 1) : 0) . "%\n";
    echo "路由器活跃率: " . (count($zones) > 0 ? round(($activeRouters / count($zones)) * 100, 1) : 0) . "%\n";
    
    // 显示未分配区域的设备
    $unassignedDevices = $deviceStats['unassigned_devices'] ?? 0;
    if ($unassignedDevices > 0) {
        echo "\n⚠️  发现 {$unassignedDevices} 个设备未分配到任何路由器区域\n";
        echo "建议运行以下命令来分配这些设备到默认区域:\n";
        echo "php scripts/create_default_zone.php\n";
    }
    
    echo "\n=== 测试完成 ===\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
} 