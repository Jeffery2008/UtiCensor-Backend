<?php
/**
 * Simple PHP test server for UtiCensor API
 * Run with: php -S 0.0.0.0:8080 -t public test_server.php
 */

require_once __DIR__ . '/vendor/autoload.php';
use UtiCensor\Utils\Logger;

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Shanghai');

// Get request info
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// 记录测试服务器请求
Logger::apiRequestLog(
    $requestMethod,
    $_SERVER['REQUEST_URI'],
    $requestMethod === 'GET' ? $_GET : $_POST,
    null,
    null,
    null
);

// Remove query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove base path if exists
$basePath = '/api';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Simple mock responses
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Mock data
$mockUser = [
    'id' => 1,
    'username' => 'admin',
    'email' => 'admin@uticensor.local',
    'role' => 'admin',
    'created_at' => '2025-01-01 00:00:00',
    'last_login_at' => date('Y-m-d H:i:s')
];

$mockDevices = [
    [
        'id' => 1,
        'device_name' => 'Router-Main',
        'device_identifier' => 'router-001',
        'mac_address' => 'aa:bb:cc:dd:ee:ff',
        'ip_address' => '192.168.1.1',
        'device_type' => 'Router',
        'location' => 'Main Office',
        'description' => 'Main office router',
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00'
    ],
    [
        'id' => 2,
        'device_name' => 'Switch-Floor1',
        'device_identifier' => 'switch-001',
        'mac_address' => '11:22:33:44:55:66',
        'ip_address' => '192.168.1.10',
        'device_type' => 'Switch',
        'location' => 'Floor 1',
        'description' => 'Floor 1 network switch',
        'is_active' => 1,
        'created_at' => '2025-01-01 00:00:00'
    ]
];

$mockStats = [
    [
        'date' => '2025-01-20',
        'flow_count' => 1250,
        'total_bytes' => 52428800,
        'unique_local_ips' => 15,
        'unique_remote_ips' => 89
    ],
    [
        'date' => '2025-01-21',
        'flow_count' => 1380,
        'total_bytes' => 61440000,
        'unique_local_ips' => 18,
        'unique_remote_ips' => 95
    ],
    [
        'date' => '2025-01-22',
        'flow_count' => 1120,
        'total_bytes' => 45875200,
        'unique_local_ips' => 12,
        'unique_remote_ips' => 78
    ]
];

$mockHourlyStats = [];
for ($i = 0; $i < 24; $i++) {
    $mockHourlyStats[] = [
        'hour' => $i,
        'flow_count' => rand(20, 150),
        'total_bytes' => rand(1048576, 10485760)
    ];
}

$mockTopApps = [
    ['application' => 'HTTPS', 'flow_count' => 450, 'total_bytes' => 25165824],
    ['application' => 'HTTP', 'flow_count' => 320, 'total_bytes' => 15728640],
    ['application' => 'DNS', 'flow_count' => 280, 'total_bytes' => 1048576],
    ['application' => 'YouTube', 'flow_count' => 150, 'total_bytes' => 52428800],
    ['application' => 'SSH', 'flow_count' => 85, 'total_bytes' => 524288]
];

$mockTopProtocols = [
    ['protocol' => 'TCP', 'flow_count' => 850, 'total_bytes' => 67108864],
    ['protocol' => 'UDP', 'flow_count' => 420, 'total_bytes' => 8388608],
    ['protocol' => 'ICMP', 'flow_count' => 65, 'total_bytes' => 65536]
];

$mockTopHosts = [
    ['host' => 'www.google.com', 'flow_count' => 125, 'total_bytes' => 8388608],
    ['host' => 'www.youtube.com', 'flow_count' => 98, 'total_bytes' => 52428800],
    ['host' => 'api.github.com', 'flow_count' => 76, 'total_bytes' => 2097152],
    ['host' => 'cdn.jsdelivr.net', 'flow_count' => 54, 'total_bytes' => 4194304]
];

// Route handling
try {
    switch (true) {
        // Auth routes
        case $path === '/auth/login' && $requestMethod === 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input['username'] === 'admin' && $input['password'] === 'admin') {
                jsonResponse([
                    'token' => 'mock_jwt_token_' . time(),
                    'user' => $mockUser
                ]);
            } else {
                jsonResponse(['error' => 'Invalid credentials'], 401);
            }
            break;
            
        case $path === '/auth/me' && $requestMethod === 'GET':
            jsonResponse(['user' => $mockUser]);
            break;

        // Device routes
        case $path === '/devices' && $requestMethod === 'GET':
            jsonResponse([
                'devices' => $mockDevices,
                'pagination' => [
                    'page' => 1,
                    'limit' => 50,
                    'total' => count($mockDevices),
                    'pages' => 1
                ]
            ]);
            break;

        // Flow stats routes
        case $path === '/flows/stats' && $requestMethod === 'GET':
            jsonResponse([
                'device_id' => $_GET['device_id'] ?? 1,
                'date_range' => [
                    'start_date' => $_GET['start_date'] ?? '2025-01-20',
                    'end_date' => $_GET['end_date'] ?? '2025-01-22'
                ],
                'daily_stats' => $mockStats,
                'top_applications' => $mockTopApps,
                'top_protocols' => $mockTopProtocols,
                'top_hosts' => $mockTopHosts
            ]);
            break;
            
        case $path === '/flows/stats/hourly' && $requestMethod === 'GET':
            jsonResponse([
                'device_id' => $_GET['device_id'] ?? 1,
                'date' => $_GET['date'] ?? date('Y-m-d'),
                'hourly_stats' => $mockHourlyStats
            ]);
            break;

        case $path === '/flows' && $requestMethod === 'GET':
            jsonResponse([
                'flows' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 50,
                    'total' => 0,
                    'pages' => 0
                ]
            ]);
            break;

        // Health check
        case $path === '/health' && $requestMethod === 'GET':
            jsonResponse([
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => '2.0.0'
            ]);
            break;

        default:
            jsonResponse(['error' => 'Route not found'], 404);
            break;
    }
} catch (\Throwable $e) {
    jsonResponse([
        'error' => 'Internal server error',
        'debug' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], 500);
}

