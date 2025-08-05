<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;
use UtiCensor\Utils\Logger;

class RouterMappingController
{
    private $userModel;
    private $configFile;
    private $config;

    public function __construct()
    {
        $this->userModel = new User();
        $this->configFile = __DIR__ . '/../../config/app.php';
        $this->config = require $this->configFile;
    }

    public function index(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return;
        }

        $mappings = [
            'router_identifier_mapping' => $this->config['router_identifier_mapping'] ?? [],
            'router_mapping' => $this->config['router_mapping'] ?? [],
            'interface_mapping' => $this->config['interface_mapping'] ?? [],
            'netify_settings' => [
                'allow_unknown_devices' => $this->config['netify']['allow_unknown_devices'] ?? false,
                'allow_unknown_zones' => $this->config['netify']['allow_unknown_zones'] ?? false,
                'auto_create_devices' => $this->config['netify']['auto_create_devices'] ?? false,
                'auto_create_zones' => $this->config['netify']['auto_create_zones'] ?? false,
            ]
        ];

        $this->jsonResponse(['mappings' => $mappings]);
    }

    public function getConfig(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $mappings = [
            'router_identifier_mapping' => $this->config['router_identifier_mapping'] ?? [],
            'router_mapping' => $this->config['router_mapping'] ?? [],
            'interface_mapping' => $this->config['interface_mapping'] ?? [],
            'netify_settings' => [
                'allow_unknown_devices' => $this->config['netify']['allow_unknown_devices'] ?? false,
                'allow_unknown_zones' => $this->config['netify']['allow_unknown_zones'] ?? false,
                'auto_create_devices' => $this->config['netify']['auto_create_devices'] ?? false,
                'auto_create_zones' => $this->config['netify']['auto_create_zones'] ?? false,
            ]
        ];

        $this->jsonResponse(['mappings' => $mappings]);
    }

    public function update(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input)) {
            $this->jsonResponse(['error' => 'No data provided'], 400);
            return;
        }

        try {
            $updated = false;
            $newConfig = $this->config;

            // 更新路由器标识符映射
            if (isset($input['router_identifier_mapping'])) {
                $newConfig['router_identifier_mapping'] = $this->validateMapping($input['router_identifier_mapping']);
                $updated = true;
            }

            // 更新路由器映射
            if (isset($input['router_mapping'])) {
                $newConfig['router_mapping'] = $this->validateMapping($input['router_mapping']);
                $updated = true;
            }

            // 更新接口映射
            if (isset($input['interface_mapping'])) {
                $newConfig['interface_mapping'] = $this->validateMapping($input['interface_mapping']);
                $updated = true;
            }

            // 更新Netify设置
            if (isset($input['netify_settings'])) {
                $settings = $input['netify_settings'];
                $newConfig['netify']['allow_unknown_devices'] = (bool)($settings['allow_unknown_devices'] ?? false);
                $newConfig['netify']['allow_unknown_zones'] = (bool)($settings['allow_unknown_zones'] ?? false);
                $newConfig['netify']['auto_create_devices'] = (bool)($settings['auto_create_devices'] ?? false);
                $newConfig['netify']['auto_create_zones'] = (bool)($settings['auto_create_zones'] ?? false);
                $updated = true;
            }

            if ($updated) {
                $this->saveConfig($newConfig);
                
                Logger::info("路由器映射配置更新成功", 'router_mapping', [
                    'updated_by' => $this->getCurrentUser()['id'] ?? null,
                    'updated_sections' => array_keys(array_filter([
                        'router_identifier_mapping' => isset($input['router_identifier_mapping']),
                        'router_mapping' => isset($input['router_mapping']),
                        'interface_mapping' => isset($input['interface_mapping']),
                        'netify_settings' => isset($input['netify_settings'])
                    ]))
                ]);
                
                $this->jsonResponse(['message' => 'Router mapping configuration updated successfully']);
            } else {
                $this->jsonResponse(['error' => 'No valid configuration provided'], 400);
            }

        } catch (\Throwable $e) {
            Logger::error("路由器映射配置更新失败", 'router_mapping', [
                'error' => $e->getMessage(),
                'updated_by' => $this->getCurrentUser()['id'] ?? null
            ]);
            $this->jsonResponse(['error' => 'Failed to update configuration: ' . $e->getMessage()], 500);
        }
    }

    public function addMapping(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['type']) || empty($input['key']) || !isset($input['value'])) {
            $this->jsonResponse(['error' => 'Type, key, and value are required'], 400);
            return;
        }

        try {
            $newConfig = $this->config;
            $type = $input['type'];
            $key = $input['key'];
            $value = $input['value'];

            // 验证映射类型
            if (!in_array($type, ['router_identifier_mapping', 'router_mapping', 'interface_mapping'])) {
                $this->jsonResponse(['error' => 'Invalid mapping type'], 400);
                return;
            }

            // 验证IP地址格式
            if (!filter_var($key, FILTER_VALIDATE_IP)) {
                $this->jsonResponse(['error' => 'Invalid IP address format'], 400);
                return;
            }

            // 验证标识符格式
            if (empty($value) || strlen($value) > 100) {
                $this->jsonResponse(['error' => 'Invalid identifier format'], 400);
                return;
            }

            // 添加映射
            $newConfig[$type][$key] = $value;
            $this->saveConfig($newConfig);

            $this->jsonResponse([
                'message' => 'Mapping added successfully',
                'mapping' => [$key => $value]
            ]);

        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to add mapping: ' . $e->getMessage()], 500);
        }
    }

    public function removeMapping(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['type']) || empty($input['key'])) {
            $this->jsonResponse(['error' => 'Type and key are required'], 400);
            return;
        }

        try {
            $newConfig = $this->config;
            $type = $input['type'];
            $key = $input['key'];

            // 验证映射类型
            if (!in_array($type, ['router_identifier_mapping', 'router_mapping', 'interface_mapping'])) {
                $this->jsonResponse(['error' => 'Invalid mapping type'], 400);
                return;
            }

            // 检查映射是否存在
            if (!isset($newConfig[$type][$key])) {
                $this->jsonResponse(['error' => 'Mapping not found'], 404);
                return;
            }

            // 删除映射
            unset($newConfig[$type][$key]);
            $this->saveConfig($newConfig);

            $this->jsonResponse(['message' => 'Mapping removed successfully']);

        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to remove mapping: ' . $e->getMessage()], 500);
        }
    }

    public function testMapping(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $ip = $_GET['ip'] ?? '';
        if (empty($ip)) {
            $this->jsonResponse(['error' => 'IP address is required'], 400);
            return;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->jsonResponse(['error' => 'Invalid IP address format'], 400);
            return;
        }

        // 测试映射
        $result = [
            'ip' => $ip,
            'router_identifier_mapping' => $this->config['router_identifier_mapping'][$ip] ?? null,
            'router_mapping' => $this->config['router_mapping'][$ip] ?? null,
            'interface_mapping' => null, // 接口映射需要接口名称，这里只测试IP
        ];

        $this->jsonResponse(['test_result' => $result]);
    }

    public function getStats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $stats = [
            'router_identifier_mapping_count' => count($this->config['router_identifier_mapping'] ?? []),
            'router_mapping_count' => count($this->config['router_mapping'] ?? []),
            'interface_mapping_count' => count($this->config['interface_mapping'] ?? []),
            'total_mappings' => count($this->config['router_identifier_mapping'] ?? []) + 
                               count($this->config['router_mapping'] ?? []) + 
                               count($this->config['interface_mapping'] ?? []),
            'security_settings' => [
                'allow_unknown_devices' => $this->config['netify']['allow_unknown_devices'] ?? false,
                'allow_unknown_zones' => $this->config['netify']['allow_unknown_zones'] ?? false,
                'auto_create_devices' => $this->config['netify']['auto_create_devices'] ?? false,
                'auto_create_zones' => $this->config['netify']['auto_create_zones'] ?? false,
            ]
        ];

        $this->jsonResponse(['stats' => $stats]);
    }

    private function validateMapping(array $mapping): array
    {
        $validated = [];
        foreach ($mapping as $key => $value) {
            if (filter_var($key, FILTER_VALIDATE_IP) && !empty($value) && strlen($value) <= 100) {
                $validated[$key] = $value;
            }
        }
        return $validated;
    }

    private function saveConfig(array $config): void
    {
        $configContent = "<?php\n/**\n * UtiCensor 应用配置\n */\n\nreturn " . var_export($config, true) . ";\n";
        
        if (file_put_contents($this->configFile, $configContent) === false) {
            throw new \Exception('Failed to write configuration file');
        }
    }

    private function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    private function isAdmin(): bool
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === 'admin';
    }

    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? 
                     $headers['authorization'] ?? 
                     $_SERVER['HTTP_AUTHORIZATION'] ?? 
                     $_SERVER['HTTP_AUTHORIZATION_LOWER'] ?? 
                     $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 
                     null;
        
        // 处理Apache配置错误的情况
        if ($authHeader === '%{HTTP:Authorization}e') {
            $authHeader = null;
        }
        
        // 如果还是没有，尝试从原始输入中解析
        if (!$authHeader) {
            $input = file_get_contents('php://input');
            if (preg_match('/Authorization:\s*Bearer\s+([^\s]+)/i', $input, $matches)) {
                $authHeader = 'Bearer ' . $matches[1];
            }
        }
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $payload = JWT::decode($token);
        
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }

        return $this->userModel->findById($payload['user_id']);
    }

    public function getZones(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        try {
            $routerZoneModel = new \UtiCensor\Models\RouterZone();
            $zones = $routerZoneModel->getAll();
            $this->jsonResponse(['zones' => $zones]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to get zones: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
} 