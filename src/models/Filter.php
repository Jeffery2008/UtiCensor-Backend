<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;
use UtiCensor\Utils\Logger;

class Filter
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data, array $conditions): int
    {
        $this->db->beginTransaction();
        
        try {
            $filterId = $this->db->insert('filters', $data);
            
            foreach ($conditions as $condition) {
                $condition['filter_id'] = $filterId;
                $this->db->insert('filter_conditions', $condition);
            }
            
            $this->db->commit();
            
            Logger::info("过滤器创建成功", 'filter_management', [
                'filter_id' => $filterId,
                'filter_name' => $data['name'],
                'conditions_count' => count($conditions),
                'created_by' => $data['created_by'] ?? null
            ]);
            
            return $filterId;
        } catch (\Exception $e) {
            $this->db->rollback();
            Logger::error("过滤器创建失败", 'filter_management', [
                'filter_name' => $data['name'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $filter = $this->db->fetchOne('SELECT * FROM filters WHERE id = ?', [$id]);
        if (!$filter) {
            return null;
        }

        $filter['conditions'] = $this->getConditions($id);
        return $filter;
    }

    public function getAll(int $offset = 0, int $limit = 50, array $filters = []): array
    {
        $sql = 'SELECT f.*, u.username as created_by_username 
                FROM filters f 
                LEFT JOIN users u ON f.created_by = u.id';
        $params = [];
        $conditions = [];

        if (!empty($filters['filter_type'])) {
            $conditions[] = 'f.filter_type = ?';
            $params[] = $filters['filter_type'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'f.category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['is_active'])) {
            $conditions[] = 'f.is_active = ?';
            $params[] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(f.name LIKE ? OR f.description LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY f.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function getConditions(int $filterId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM filter_conditions WHERE filter_id = ? ORDER BY condition_order',
            [$filterId]
        );
    }

    public function update(int $id, array $data, array $conditions = null): bool
    {
        $this->db->beginTransaction();
        
        try {
            $this->db->update('filters', $data, ['id' => $id]);
            
            if ($conditions !== null) {
                // Delete existing conditions
                $this->db->delete('filter_conditions', ['filter_id' => $id]);
                
                // Insert new conditions
                foreach ($conditions as $condition) {
                    $condition['filter_id'] = $id;
                    $this->db->insert('filter_conditions', $condition);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('filters', ['id' => $id]) > 0;
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as count FROM filters';
        $params = [];
        $conditions = [];

        if (!empty($filters['filter_type'])) {
            $conditions[] = 'filter_type = ?';
            $params[] = $filters['filter_type'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = 'category = ?';
            $params[] = $filters['category'];
        }

        if (!empty($filters['is_active'])) {
            $conditions[] = 'is_active = ?';
            $params[] = $filters['is_active'];
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int) $result['count'];
    }

    public function buildQuery(int $filterId, string $baseTable = 'network_flows'): array
    {
        $filter = $this->findById($filterId);
        if (!$filter || empty($filter['conditions'])) {
            return ['sql' => '', 'params' => []];
        }

        $conditions = [];
        $params = [];
        
        foreach ($filter['conditions'] as $condition) {
            $fieldName = $condition['field_name'];
            $operator = $condition['operator'];
            $value = $condition['field_value'];
            $value2 = $condition['field_value2'];
            
            switch ($operator) {
                case '=':
                case '!=':
                case '>':
                case '<':
                case '>=':
                case '<=':
                    $conditions[] = "{$baseTable}.{$fieldName} {$operator} ?";
                    $params[] = $value;
                    break;
                    
                case 'LIKE':
                case 'NOT LIKE':
                    $conditions[] = "{$baseTable}.{$fieldName} {$operator} ?";
                    $params[] = $value;
                    break;
                    
                case 'IN':
                case 'NOT IN':
                    $values = explode(',', $value);
                    $placeholders = str_repeat('?,', count($values) - 1) . '?';
                    $conditions[] = "{$baseTable}.{$fieldName} {$operator} ({$placeholders})";
                    $params = array_merge($params, $values);
                    break;
                    
                case 'IS NULL':
                case 'IS NOT NULL':
                    $conditions[] = "{$baseTable}.{$fieldName} {$operator}";
                    break;
                    
                case 'BETWEEN':
                    $conditions[] = "{$baseTable}.{$fieldName} BETWEEN ? AND ?";
                    $params[] = $value;
                    $params[] = $value2;
                    break;
            }
        }

        if (empty($conditions)) {
            return ['sql' => '', 'params' => []];
        }

        // Group conditions by logical operator
        $sql = '(' . implode(' AND ', $conditions) . ')';
        
        return ['sql' => $sql, 'params' => $params];
    }

    public function getAvailableFields(): array
    {
        return [
            'recv_ts' => 'Receive Timestamp',
            'local_ip' => 'Local IP',
            'local_port' => 'Local Port',
            'local_mac' => 'Local MAC',
            'other_ip' => 'Remote IP',
            'other_port' => 'Remote Port',
            'other_mac' => 'Remote MAC',
            'detected_protocol_name' => 'Protocol',
            'detected_application_name' => 'Application',
            'host_server_name' => 'Host Server',
            'ip_version' => 'IP Version',
            'ip_protocol' => 'IP Protocol',
            'established' => 'Established',
            'vlan_id' => 'VLAN ID',
            'interface_name' => 'Interface',
            'bytes_len' => 'Bytes Length'
        ];
    }

    public function getOperators(): array
    {
        return [
            '=' => 'Equals',
            '!=' => 'Not Equals',
            '>' => 'Greater Than',
            '<' => 'Less Than',
            '>=' => 'Greater Than or Equal',
            '<=' => 'Less Than or Equal',
            'LIKE' => 'Contains',
            'NOT LIKE' => 'Does Not Contain',
            'IN' => 'In List',
            'NOT IN' => 'Not In List',
            'IS NULL' => 'Is Empty',
            'IS NOT NULL' => 'Is Not Empty',
            'BETWEEN' => 'Between'
        ];
    }
}

