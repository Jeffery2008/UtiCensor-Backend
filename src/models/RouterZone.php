<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;

class RouterZone
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(int $offset = 0, int $limit = 50, array $filters = []): array
    {
        $sql = "SELECT rz.*, u.username as created_by_username 
                FROM router_zones rz 
                LEFT JOIN users u ON rz.created_by = u.id 
                WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND rz.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (rz.zone_name LIKE ? OR rz.router_name LIKE ? OR rz.router_identifier LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " ORDER BY rz.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM router_zones rz WHERE 1=1";
        $params = [];

        if (isset($filters['is_active'])) {
            $sql .= " AND rz.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (rz.zone_name LIKE ? OR rz.router_name LIKE ? OR rz.router_identifier LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int) ($result['count'] ?? 0);
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT rz.*, u.username as created_by_username 
                FROM router_zones rz 
                LEFT JOIN users u ON rz.created_by = u.id 
                WHERE rz.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $sql = "SELECT * FROM router_zones WHERE router_identifier = ?";
        return $this->db->fetchOne($sql, [$identifier]);
    }

    public function create(array $data): int
    {
        $insertData = [
            'zone_name' => $data['zone_name'],
            'router_identifier' => $data['router_identifier'],
            'router_name' => $data['router_name'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_by' => $data['created_by'] ?? null
        ];
        
        return $this->db->insert('router_zones', $insertData);
    }

    public function update(int $id, array $data): bool
    {
        $allowedFields = ['zone_name', 'router_identifier', 'router_name', 'description', 'is_active'];
        $updateFields = array_intersect_key($data, array_flip($allowedFields));
        
        if (empty($updateFields)) {
            return false;
        }

        $updateFields['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('router_zones', $updateFields, ['id' => $id]) > 0;
    }

    public function delete(int $id): bool
    {
        // 首先将关联的设备设置为默认区域
        $sql = "UPDATE devices SET router_zone_id = (SELECT id FROM router_zones WHERE router_identifier = 'default') WHERE router_zone_id = ?";
        $this->db->query($sql, [$id]);

        // 将关联的网络流量设置为默认区域
        $sql = "UPDATE network_flows SET router_zone_id = (SELECT id FROM router_zones WHERE router_identifier = 'default') WHERE router_zone_id = ?";
        $this->db->query($sql, [$id]);

        // 删除路由器区域（不能删除默认区域）
        $sql = "DELETE FROM router_zones WHERE id = ? AND router_identifier != 'default'";
        return $this->db->query($sql, [$id])->rowCount() > 0;
    }

    public function getDeviceCount(int $zoneId): int
    {
        $sql = "SELECT COUNT(*) as count FROM devices WHERE router_zone_id = ?";
        $result = $this->db->fetchOne($sql, [$zoneId]);
        return (int) ($result['count'] ?? 0);
    }

    public function getFlowCount(int $zoneId): int
    {
        $sql = "SELECT COUNT(*) as count FROM network_flows WHERE router_zone_id = ?";
        $result = $this->db->fetchOne($sql, [$zoneId]);
        return (int) ($result['count'] ?? 0);
    }

    public function getZoneStats(): array
    {
        $sql = "SELECT 
                    rz.id,
                    rz.zone_name,
                    rz.router_name,
                    COUNT(DISTINCT d.id) as device_count,
                    COUNT(nf.id) as flow_count
                FROM router_zones rz
                LEFT JOIN devices d ON rz.id = d.router_zone_id
                LEFT JOIN network_flows nf ON rz.id = nf.router_zone_id
                WHERE rz.is_active = 1
                GROUP BY rz.id, rz.zone_name, rz.router_name
                ORDER BY rz.created_at DESC";
        
        return $this->db->fetchAll($sql);
    }
} 