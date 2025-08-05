<?php

require_once __DIR__ . '/vendor/autoload.php';

use UtiCensor\Utils\Logger;

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'UtiCensor\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use UtiCensor\Services\NetifyIngestService;

// Set timezone
date_default_timezone_set('Asia/Shanghai');

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    Logger::systemError($message, null, [
        'code' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

set_exception_handler(function($exception) {
    Logger::exception($exception, 'netify_service');
});

// 添加启动日志
echo date('c') . " Starting UtiCensor Netify Ingest Service...\n";
echo date('c') . " Timezone: " . date_default_timezone_get() . "\n";

try {
    Logger::info("Netify 服务启动", 'netify_service', [
        'timezone' => date_default_timezone_get()
    ]);
    
    $service = new NetifyIngestService();
    
    // 输出配置信息
    $config = require __DIR__ . '/config/app.php';
    echo date('c') . " Netify configuration:\n";
    echo date('c') . "   Listen: {$config['netify']['listen_host']}:{$config['netify']['listen_port']}\n";
    echo date('c') . "   Buffer size: {$config['netify']['buffer_size']}\n";
    echo date('c') . "   Auto create zones: " . ($config['netify']['auto_create_zones'] ? 'enabled' : 'disabled') . "\n";
    echo date('c') . "   Auto create devices: " . ($config['netify']['auto_create_devices'] ? 'enabled' : 'disabled') . "\n";
    echo date('c') . "   Allow unknown devices: " . ($config['netify']['allow_unknown_devices'] ? 'enabled' : 'disabled') . "\n";
    echo date('c') . "   Allow unknown zones: " . ($config['netify']['allow_unknown_zones'] ? 'enabled' : 'disabled') . "\n";
    echo date('c') . "   Prefer router identifier: " . ($config['netify']['prefer_router_identifier'] ? 'enabled' : 'disabled') . "\n";
    echo date('c') . "   Generate dynamic identifier: " . ($config['netify']['generate_dynamic_identifier'] ? 'enabled' : 'disabled') . "\n";
    
    // 显示路由器映射配置
    if (!empty($config['router_identifier_mapping'])) {
        echo date('c') . " Router identifier mappings configured: " . count($config['router_identifier_mapping']) . " entries\n";
    }
    
    if (!empty($config['router_mapping'])) {
        echo date('c') . " Router IP mappings configured: " . count($config['router_mapping']) . " entries\n";
    }
    
    echo date('c') . " Starting listener...\n";
    $service->startListener();
    
} catch (\Throwable $e) {
    Logger::error("Netify 服务致命错误", 'netify_service', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    fwrite(STDERR, date('c') . " Fatal error: " . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, date('c') . " Stack trace: " . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}

