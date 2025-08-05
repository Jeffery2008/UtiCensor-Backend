<?php
/**
 * 测试API路由是否正常工作
 */

require_once __DIR__ . '/vendor/autoload.php';

use UtiCensor\Utils\Logger;

// 记录测试开始
Logger::info("开始测试API路由", 'route_testing');

$baseUrl = 'http://localhost:8080/api'; // 根据实际情况调整
$routes = [
    '/logs',
    '/logs/stats',
    '/flows',
    '/flows/stats',
    '/flows/zones',
    '/flows/devices',
    '/flows/top-destinations',
    '/flows/anomalies',
    '/flows/protocol-distribution',
    '/flows/export',
    '/devices',
    '/router-zones',
    '/filters',
    '/auth/me'
];

echo "测试API路由...\n";
echo "基础URL: $baseUrl\n\n";

foreach ($routes as $route) {
    $url = $baseUrl . $route;
    echo "测试路由: $route\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ 错误: $error\n";
    } else {
        if ($httpCode === 404) {
            echo "  ❌ 404 - 路由未找到\n";
        } else if ($httpCode === 401) {
            echo "  ⚠️  401 - 需要认证 (正常)\n";
        } else if ($httpCode === 200) {
            echo "  ✅ 200 - 路由正常\n";
        } else {
            echo "  ⚠️  $httpCode - 其他状态码\n";
        }
    }
    
    echo "\n";
}

echo "路由测试完成！\n";

// 记录测试完成
Logger::info("API路由测试完成", 'route_testing'); 