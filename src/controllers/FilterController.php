<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\Filter;
use UtiCensor\Models\User;
use UtiCensor\Utils\JWT;
use UtiCensor\Utils\Logger;

class FilterController
{
    private $filterModel;
    private $userModel;

    public function __construct()
    {
        $this->filterModel = new Filter();
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
            'filter_type' => $_GET['filter_type'] ?? null,
            'category' => $_GET['category'] ?? null,
            'is_active' => $_GET['is_active'] ?? null,
            'search' => $_GET['search'] ?? null
        ];

        $filterList = $this->filterModel->getAll($offset, $limit, $filters);
        $total = $this->filterModel->count($filters);

        $this->jsonResponse([
            'filters' => $filterList,
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
            $this->jsonResponse(['error' => 'Filter ID is required'], 400);
            return;
        }

        $filter = $this->filterModel->findById($id);
        if (!$filter) {
            $this->jsonResponse(['error' => 'Filter not found'], 404);
            return;
        }

        $this->jsonResponse(['filter' => $filter]);
    }

    public function create(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['name']) || empty($input['conditions'])) {
            $this->jsonResponse(['error' => 'Filter name and conditions are required'], 400);
            return;
        }

        if (!is_array($input['conditions']) || count($input['conditions']) === 0) {
            $this->jsonResponse(['error' => 'At least one condition is required'], 400);
            return;
        }

        if (count($input['conditions']) > 20) {
            $this->jsonResponse(['error' => 'Maximum 20 conditions allowed'], 400);
            return;
        }

        // Validate conditions
        $validationResult = $this->validateConditions($input['conditions']);
        if ($validationResult !== true) {
            $this->jsonResponse(['error' => $validationResult], 400);
            return;
        }

        $currentUser = $this->getCurrentUser();
        $filterData = [
            'name' => $input['name'],
            'description' => $input['description'] ?? null,
            'filter_type' => 'custom',
            'category' => $input['category'] ?? null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1,
            'created_by' => $currentUser['id']
        ];

        // Prepare conditions
        $conditions = [];
        foreach ($input['conditions'] as $index => $condition) {
            $conditions[] = [
                'field_name' => $condition['field_name'],
                'operator' => $condition['operator'],
                'field_value' => $condition['field_value'] ?? null,
                'field_value2' => $condition['field_value2'] ?? null,
                'logical_operator' => $condition['logical_operator'] ?? 'AND',
                'condition_order' => $index
            ];
        }

        try {
            $filterId = $this->filterModel->create($filterData, $conditions);
            $filter = $this->filterModel->findById($filterId);
            
            Logger::info("过滤器创建成功", 'filter_management', [
                'filter_id' => $filterId,
                'filter_name' => $filterData['name'],
                'conditions_count' => count($conditions),
                'created_by' => $currentUser['id']
            ]);
            
            $this->jsonResponse([
                'message' => 'Filter created successfully',
                'filter' => $filter
            ], 201);
        } catch (\Throwable $e) {
            Logger::error("过滤器创建失败", 'filter_management', [
                'filter_name' => $filterData['name'],
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Failed to create filter'], 500);
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
            $this->jsonResponse(['error' => 'Filter ID is required'], 400);
            return;
        }

        $filter = $this->filterModel->findById($id);
        if (!$filter) {
            $this->jsonResponse(['error' => 'Filter not found'], 404);
            return;
        }

        // Check permissions - only admin or creator can modify
        $currentUser = $this->getCurrentUser();
        if ($currentUser['role'] !== 'admin' && $filter['created_by'] != $currentUser['id']) {
            $this->jsonResponse(['error' => 'You can only modify your own filters'], 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $allowedFields = ['name', 'description', 'category', 'is_active'];
        $updateData = array_intersect_key($input, array_flip($allowedFields));

        $conditions = null;
        if (isset($input['conditions'])) {
            if (!is_array($input['conditions'])) {
                $this->jsonResponse(['error' => 'Conditions must be an array'], 400);
                return;
            }

            if (count($input['conditions']) > 20) {
                $this->jsonResponse(['error' => 'Maximum 20 conditions allowed'], 400);
                return;
            }

            // Validate conditions
            $validationResult = $this->validateConditions($input['conditions']);
            if ($validationResult !== true) {
                $this->jsonResponse(['error' => $validationResult], 400);
                return;
            }

            $conditions = [];
            foreach ($input['conditions'] as $index => $condition) {
                $conditions[] = [
                    'field_name' => $condition['field_name'],
                    'operator' => $condition['operator'],
                    'field_value' => $condition['field_value'] ?? null,
                    'field_value2' => $condition['field_value2'] ?? null,
                    'logical_operator' => $condition['logical_operator'] ?? 'AND',
                    'condition_order' => $index
                ];
            }
        }

        try {
            $this->filterModel->update($id, $updateData, $conditions);
            $updatedFilter = $this->filterModel->findById($id);
            
            Logger::info("过滤器更新成功", 'filter_management', [
                'filter_id' => $id,
                'filter_name' => $filter['name'],
                'updated_fields' => array_keys($updateData),
                'updated_by' => $currentUser['id']
            ]);
            
            $this->jsonResponse([
                'message' => 'Filter updated successfully',
                'filter' => $updatedFilter
            ]);
        } catch (\Throwable $e) {
            Logger::error("过滤器更新失败", 'filter_management', [
                'filter_id' => $id,
                'filter_name' => $filter['name'],
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Failed to update filter'], 500);
        }
    }

    public function delete(): void
    {
        if (!$this->isAdminOrUser()) {
            $this->jsonResponse(['error' => 'Insufficient permissions'], 403);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Filter ID is required'], 400);
            return;
        }

        $filter = $this->filterModel->findById($id);
        if (!$filter) {
            $this->jsonResponse(['error' => 'Filter not found'], 404);
            return;
        }

        // Check permissions - only admin or creator can delete
        $currentUser = $this->getCurrentUser();
        if ($currentUser['role'] !== 'admin' && $filter['created_by'] != $currentUser['id']) {
            $this->jsonResponse(['error' => 'You can only delete your own filters'], 403);
            return;
        }

        // Prevent deletion of predefined filters
        if ($filter['filter_type'] === 'predefined' && $currentUser['role'] !== 'admin') {
            $this->jsonResponse(['error' => 'Cannot delete predefined filters'], 403);
            return;
        }

        try {
            $this->filterModel->delete($id);
            
            Logger::info("过滤器删除成功", 'filter_management', [
                'filter_id' => $id,
                'filter_name' => $filter['name'],
                'deleted_by' => $currentUser['id']
            ]);
            
            $this->jsonResponse(['message' => 'Filter deleted successfully']);
        } catch (\Throwable $e) {
            Logger::error("过滤器删除失败", 'filter_management', [
                'filter_id' => $id,
                'filter_name' => $filter['name'],
                'error' => $e->getMessage()
            ]);
            $this->jsonResponse(['error' => 'Failed to delete filter'], 500);
        }
    }

    public function getFields(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $fields = $this->filterModel->getAvailableFields();
        $this->jsonResponse(['fields' => $fields]);
    }

    public function getOperators(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $operators = $this->filterModel->getOperators();
        $this->jsonResponse(['operators' => $operators]);
    }

    public function testFilter(): void
    {
        if (!$this->isAuthenticated()) {
            $this->jsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->jsonResponse(['error' => 'Filter ID is required'], 400);
            return;
        }

        $filter = $this->filterModel->findById($id);
        if (!$filter) {
            $this->jsonResponse(['error' => 'Filter not found'], 404);
            return;
        }

        try {
            $query = $this->filterModel->buildQuery($id);
            $this->jsonResponse([
                'filter' => $filter,
                'generated_query' => $query
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse(['error' => 'Failed to build query: ' . $e->getMessage()], 500);
        }
    }

    private function validateConditions(array $conditions): string|bool
    {
        $availableFields = array_keys($this->filterModel->getAvailableFields());
        $availableOperators = array_keys($this->filterModel->getOperators());

        foreach ($conditions as $condition) {
            if (empty($condition['field_name']) || empty($condition['operator'])) {
                return 'Field name and operator are required for all conditions';
            }

            if (!in_array($condition['field_name'], $availableFields)) {
                return 'Invalid field name: ' . $condition['field_name'];
            }

            if (!in_array($condition['operator'], $availableOperators)) {
                return 'Invalid operator: ' . $condition['operator'];
            }

            // Check if value is required for the operator
            $operatorsRequiringValue = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN'];
            if (in_array($condition['operator'], $operatorsRequiringValue) && empty($condition['field_value'])) {
                return 'Value is required for operator: ' . $condition['operator'];
            }

            // Check if second value is required for BETWEEN operator
            if ($condition['operator'] === 'BETWEEN' && empty($condition['field_value2'])) {
                return 'Second value is required for BETWEEN operator';
            }

            // Validate logical operator
            if (isset($condition['logical_operator']) && !in_array($condition['logical_operator'], ['AND', 'OR'])) {
                return 'Invalid logical operator: ' . $condition['logical_operator'];
            }
        }

        return true;
    }

    private function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    private function isAdminOrUser(): bool
    {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role'], ['admin', 'user']);
    }

    private function getCurrentUser(): ?array
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
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

