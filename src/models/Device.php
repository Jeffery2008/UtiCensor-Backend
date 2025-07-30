<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;

class Device
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        return $this->db->insert('devices', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM devices WHERE id = ?', [$id]);
    }

    public function findByIdentifier(string $identifier): ?array
    {
        return $this->db->fetchOne('SELECT * FROM devices WHERE device_identifier = ?', [$identifier]);
    }

    public function findByMac(string $mac): ?array
    {
        return $this->db->fetchOne('SELECT * FROM devices WHERE mac_address = ?', [$mac]);
    }

    public function getAll(int $offset = 0, int $limit = 50, array $filters = []): array
    {
        $sql = 'SELECT d.*, u.username as created_by_username, rz.zone_name, rz.router_name
                FROM devices d 
                LEFT JOIN users u ON d.created_by = u.id 
                LEFT JOIN router_zones rz ON d.router_zone_id = rz.id';
        $params = [];
        $conditions = [];

        if (!empty($filters['is_active'])) {
            $conditions[] = 'd.is_active = ?';
            $params[] = $filters['is_active'];
        }

        if (!empty($filters['device_type'])) {
            $conditions[] = 'd.device_type = ?';
            $params[] = $filters['device_type'];
        }

        if (!empty($filters['router_zone_id'])) {
            $conditions[] = 'd.router_zone_id = ?';
            $params[] = $filters['router_zone_id'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(d.device_name LIKE ? OR d.device_identifier LIKE ? OR d.mac_address LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY d.created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function update(int $id, array $data): bool
    {
        return $this->db->update('devices', $data, ['id' => $id]) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('devices', ['id' => $id]) > 0;
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as count FROM devices';
        $params = [];
        $conditions = [];

        if (!empty($filters['is_active'])) {
            $conditions[] = 'is_active = ?';
            $params[] = $filters['is_active'];
        }

        if (!empty($filters['device_type'])) {
            $conditions[] = 'device_type = ?';
            $params[] = $filters['device_type'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(device_name LIKE ? OR device_identifier LIKE ? OR mac_address LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int) $result['count'];
    }

    public function getInterfaces(int $deviceId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM network_interfaces WHERE device_id = ? ORDER BY interface_name',
            [$deviceId]
        );
    }

    public function addInterface(int $deviceId, array $data): int
    {
        $data['device_id'] = $deviceId;
        return $this->db->insert('network_interfaces', $data);
    }

    public function updateInterface(int $interfaceId, array $data): bool
    {
        return $this->db->update('network_interfaces', $data, ['id' => $interfaceId]) > 0;
    }

    public function deleteInterface(int $interfaceId): bool
    {
        return $this->db->delete('network_interfaces', ['id' => $interfaceId]) > 0;
    }

    public function getDeviceTypes(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT device_type FROM devices WHERE device_type IS NOT NULL ORDER BY device_type'
        );
    }

    public function autoDetectFromMac(string $mac, string $deviceName = null): ?int
    {
        // Check if device already exists
        $existing = $this->findByMac($mac);
        if ($existing) {
            return $existing['id'];
        }

        // Create new device
        $data = [
            'device_name' => $deviceName ?: 'Device-' . substr(str_replace(':', '', $mac), 0, 8),
            'device_identifier' => $mac,
            'mac_address' => $mac,
            'device_type' => 'Unknown',
            'description' => 'Auto-detected device from MAC: ' . $mac,
            'is_active' => 1
        ];

        return $this->create($data);
    }
}

