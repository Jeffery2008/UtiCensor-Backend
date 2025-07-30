<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\Device;
use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;

class DeviceController
{
    private $deviceModel;
    private $userModel;

    public function __construct()
    {
        $this->deviceModel = new Device();
        $this->userModel = new User();
    }

    public function index(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $limit = min((int) ($_GET['limit'] ?? 50), 100);
        $offset = ($page - 1) * $limit;

        $filters = [
            'is_active' => $_GET['is_active'] ?? null,
            'device_type' => $_GET['device_type'] ?? null,
            'search' => $_GET['search'] ?? null
        ];

        $devices = $this->deviceModel->getAll($offset, $limit, $filters);
        $total = $this->deviceModel->count($filters);

        $this->jsonResponse([
            'devices' => $devices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    public function show(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        $device = $this->deviceModel->findById($id);
        if (!$device) {
            $this->jsonResponse(['error' => 'Device not found'], 404);
            return;
        }

        $device['interfaces'] = $this->deviceModel->getInterfaces($id);

        $this->jsonResponse(['device' => $device]);
    }

    public function create(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['device_name']) || empty($input['device_identifier'])) {
            $this->jsonResponse(['error' => 'Device name and identifier are required'], 400);
            return;
        }

        // Check if device identifier already exists
        if ($this->deviceModel->findByIdentifier($input['device_identifier'])) {
            $this->jsonResponse(['error' => 'Device identifier already exists'], 409);
            return;
        }

        // Validate MAC address format if provided
        if (!empty($input['mac_address']) && !$this->isValidMacAddress($input['mac_address'])) {
            $this->jsonResponse(['error' => 'Invalid MAC address format'], 400);
            return;
        }

        $currentUser = $this->getCurrentUser();
        $data = [
            'device_name' => $input['device_name'],
            'device_identifier' => $input['device_identifier'],
            'mac_address' => $input['mac_address'] ?? null,
            'ip_address' => $input['ip_address'] ?? null,
            'device_type' => $input['device_type'] ?? 'Unknown',
            'location' => $input['location'] ?? null,
            'description' => $input['description'] ?? null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            'created_by' => $currentUser['id']
        ];

        try {
            $deviceId = $this->deviceModel->create($data);
            $device = $this->deviceModel->findById($deviceId);
            
            $this->jsonResponse([
                'message' => 'Device created successfully',
                'device' => $device
            ], 201);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to create device'], 500);
        }
    }

    public function update(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        $device = $this->deviceModel->findById($id);
        if (!$device) {
            $this->jsonResponse(['error' => 'Device not found'], 404);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if device identifier is being changed and already exists
        if (!empty($input['device_identifier']) && $input['device_identifier'] !== $device['device_identifier']) {
            if ($this->deviceModel->findByIdentifier($input['device_identifier'])) {
                $this->jsonResponse(['error' => 'Device identifier already exists'], 409);
                return;
            }
        }

        // Validate MAC address format if provided
        if (!empty($input['mac_address']) && !$this->isValidMacAddress($input['mac_address'])) {
            $this->jsonResponse(['error' => 'Invalid MAC address format'], 400);
            return;
        }

        $allowedFields = ['device_name', 'device_identifier', 'mac_address', 'ip_address', 'device_type', 'location', 'description', 'is_active'];
        $updateData = array_intersect_key($input, array_flip($allowedFields));

        try {
            $this->deviceModel->update($id, $updateData);
            $updatedDevice = $this->deviceModel->findById($id);
            
            $this->jsonResponse([
                'message' => 'Device updated successfully',
                'device' => $updatedDevice
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to update device'], 500);
        }
    }

    public function delete(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        $device = $this->deviceModel->findById($id);
        if (!$device) {
            $this->jsonResponse(['error' => 'Device not found'], 404);
            return;
        }

        try {
            $this->deviceModel->delete($id);
            $this->jsonResponse(['message' => 'Device deleted successfully']);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to delete device'], 500);
        }
    }

    public function getTypes(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $types = $this->deviceModel->getDeviceTypes();
        $this->jsonResponse(['device_types' => $types]);
    }

    public function getStats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $stats = $this->deviceModel->getDeviceStats();
        $this->jsonResponse(['stats' => $stats]);
    }

    public function addInterface(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        if (!$deviceId) {
            $this->jsonResponse(['error' => 'Device ID is required'], 400);
            return;
        }

        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            $this->jsonResponse(['error' => 'Device not found'], 404);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['interface_name'])) {
            $this->jsonResponse(['error' => 'Interface name is required'], 400);
            return;
        }

        $data = [
            'interface_name' => $input['interface_name'],
            'interface_type' => $input['interface_type'] ?? 'ethernet',
            'description' => $input['description'] ?? null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ];

        try {
            $interfaceId = $this->deviceModel->addInterface($deviceId, $data);
            $this->jsonResponse([
                'message' => 'Interface added successfully',
                'interface_id' => $interfaceId
            ], 201);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Failed to add interface'], 500);
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

    private function isAdminOrUser(): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], ['admin', 'user']);
    }

    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        error_log("Debug: All headers: " . json_encode($headers));
        
        // 尝试多种方式获取Authorization头
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
        
        error_log("Debug: Authorization header: " . ($authHeader ?? 'null'));
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            error_log("Debug: No valid Authorization header found");
            return null;
        }

        $token = $matches[1];
        error_log("Debug: Extracted token: " . substr($token, 0, 50) . "...");
        
        $payload = JWT::decode($token);
        error_log("Debug: JWT decode result: " . json_encode($payload));
        
        if (!$payload || !isset($payload['user_id'])) {
            error_log("Debug: Invalid payload or missing user_id");
            return null;
        }

        $user = $this->userModel->findById($payload['user_id']);
        error_log("Debug: User found: " . json_encode($user));
        
        return $user;
    }

    private function isValidMacAddress(string $mac): bool
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

