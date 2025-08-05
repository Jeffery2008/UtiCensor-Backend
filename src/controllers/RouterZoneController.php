<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\RouterZone;
use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;
use UtiCensor\Utils\Logger;

class RouterZoneController
{
    private $routerZoneModel;
    private $userModel;

    public function __construct()
    {
        $this->routerZoneModel = new RouterZone();
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
            'search' => $_GET['search'] ?? null
        ];

        $zones = $this->routerZoneModel->getAll($offset, $limit, $filters);
        $total = $this->routerZoneModel->count($filters);

        $this->jsonResponse([
            'zones' => $zones,
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
            $this->jsonResponse(['error' => 'Zone ID is required'], 400);
            return;
        }

        $zone = $this->routerZoneModel->findById($id);
        if (!$zone) {
            $this->jsonResponse(['error' => 'Zone not found'], 404);
            return;
        }

        // 获取区域统计信息
        $zone['device_count'] = $this->routerZoneModel->getDeviceCount($id);
        $zone['flow_count'] = $this->routerZoneModel->getFlowCount($id);

        $this->jsonResponse(['zone' => $zone]);
    }

    public function create(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['zone_name']) || empty($input['router_identifier'])) {
            $this->jsonResponse(['error' => 'Zone name and router identifier are required'], 400);
            return;
        }

        // Check if router identifier already exists
        if ($this->routerZoneModel->findByIdentifier($input['router_identifier'])) {
            $this->jsonResponse(['error' => 'Router identifier already exists'], 409);
            return;
        }

        $currentUser = $this->getCurrentUser();
        $data = [
            'zone_name' => $input['zone_name'],
            'router_identifier' => $input['router_identifier'],
            'router_name' => $input['router_name'] ?? null,
            'description' => $input['description'] ?? null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            'created_by' => $currentUser['id']
        ];

        try {
            $zoneId = $this->routerZoneModel->create($data);
            $zone = $this->routerZoneModel->findById($zoneId);
            
            $this->jsonResponse([
                'message' => 'Router zone created successfully',
                'zone' => $zone
            ], 201);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to create router zone'], 500);
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
            $this->jsonResponse(['error' => 'Zone ID is required'], 400);
            return;
        }

        $zone = $this->routerZoneModel->findById($id);
        if (!$zone) {
            $this->jsonResponse(['error' => 'Zone not found'], 404);
            return;
        }

        // 不允许修改默认区域
        if ($zone['router_identifier'] === 'default') {
            $this->jsonResponse(['error' => 'Cannot modify default zone'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if router identifier is being changed and already exists
        if (!empty($input['router_identifier']) && $input['router_identifier'] !== $zone['router_identifier']) {
            if ($this->routerZoneModel->findByIdentifier($input['router_identifier'])) {
                $this->jsonResponse(['error' => 'Router identifier already exists'], 409);
                return;
            }
        }

        $allowedFields = ['zone_name', 'router_identifier', 'router_name', 'description', 'is_active'];
        $updateData = array_intersect_key($input, array_flip($allowedFields));

        try {
            $this->routerZoneModel->update($id, $updateData);
            $updatedZone = $this->routerZoneModel->findById($id);
            
            $this->jsonResponse([
                'message' => 'Router zone updated successfully',
                'zone' => $updatedZone
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to update router zone'], 500);
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
            $this->jsonResponse(['error' => 'Zone ID is required'], 400);
            return;
        }

        $zone = $this->routerZoneModel->findById($id);
        if (!$zone) {
            $this->jsonResponse(['error' => 'Zone not found'], 404);
            return;
        }

        // 不允许删除默认区域
        if ($zone['router_identifier'] === 'default') {
            $this->jsonResponse(['error' => 'Cannot delete default zone'], 403);
            return;
        }

        try {
            $this->routerZoneModel->delete($id);
            $this->jsonResponse(['message' => 'Router zone deleted successfully']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to delete router zone'], 500);
        }
    }

    public function stats(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $stats = $this->routerZoneModel->getZoneStats();
        $this->jsonResponse(['stats' => $stats]);
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

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
} 