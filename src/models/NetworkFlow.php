<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;

class NetworkFlow
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create(array $data): int
    {
        return $this->db->insert('network_flows', $data);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM network_flows WHERE id = ?', [$id]);
    }

    public function getAll(int $offset = 0, int $limit = 50, array $filters = []): array
    {
        $sql = 'SELECT nf.*, d.device_name, d.device_identifier 
                FROM network_flows nf 
                LEFT JOIN devices d ON nf.device_id = d.id';
        $params = [];
        $conditions = [];

        // Apply filters
        if (!empty($filters['device_id'])) {
            $conditions[] = 'nf.device_id = ?';
            $params[] = $filters['device_id'];
        }

        if (!empty($filters['start_time'])) {
            $conditions[] = 'nf.recv_ts >= ?';
            $params[] = $filters['start_time'];
        }

        if (!empty($filters['end_time'])) {
            $conditions[] = 'nf.recv_ts <= ?';
            $params[] = $filters['end_time'];
        }

        if (!empty($filters['local_ip'])) {
            $conditions[] = 'nf.local_ip = ?';
            $params[] = $filters['local_ip'];
        }

        if (!empty($filters['other_ip'])) {
            $conditions[] = 'nf.other_ip = ?';
            $params[] = $filters['other_ip'];
        }

        if (!empty($filters['application'])) {
            $conditions[] = 'nf.detected_application_name = ?';
            $params[] = $filters['application'];
        }

        if (!empty($filters['protocol'])) {
            $conditions[] = 'nf.detected_protocol_name = ?';
            $params[] = $filters['protocol'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(nf.host_server_name LIKE ? OR nf.local_ip LIKE ? OR nf.other_ip LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY nf.recv_ts DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    public function getByFilter(int $filterId, int $offset = 0, int $limit = 50): array
    {
        $filterModel = new Filter();
        $filterQuery = $filterModel->buildQuery($filterId);
        
        if (empty($filterQuery['sql'])) {
            return [];
        }

        $sql = 'SELECT nf.*, d.device_name, d.device_identifier 
                FROM network_flows nf 
                LEFT JOIN devices d ON nf.device_id = d.id 
                WHERE ' . $filterQuery['sql'] . '
                ORDER BY nf.recv_ts DESC LIMIT ? OFFSET ?';
        
        $params = array_merge($filterQuery['params'], [$limit, $offset]);
        
        return $this->db->fetchAll($sql, $params);
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) as count FROM network_flows nf';
        $params = [];
        $conditions = [];

        // Apply same filters as getAll
        if (!empty($filters['device_id'])) {
            $conditions[] = 'nf.device_id = ?';
            $params[] = $filters['device_id'];
        }

        if (!empty($filters['start_time'])) {
            $conditions[] = 'nf.recv_ts >= ?';
            $params[] = $filters['start_time'];
        }

        if (!empty($filters['end_time'])) {
            $conditions[] = 'nf.recv_ts <= ?';
            $params[] = $filters['end_time'];
        }

        if (!empty($filters['local_ip'])) {
            $conditions[] = 'nf.local_ip = ?';
            $params[] = $filters['local_ip'];
        }

        if (!empty($filters['other_ip'])) {
            $conditions[] = 'nf.other_ip = ?';
            $params[] = $filters['other_ip'];
        }

        if (!empty($filters['application'])) {
            $conditions[] = 'nf.detected_application_name = ?';
            $params[] = $filters['application'];
        }

        if (!empty($filters['protocol'])) {
            $conditions[] = 'nf.detected_protocol_name = ?';
            $params[] = $filters['protocol'];
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $result = $this->db->fetchOne($sql, $params);
        return (int) $result['count'];
    }

    public function countByFilter(int $filterId): int
    {
        $filterModel = new Filter();
        $filterQuery = $filterModel->buildQuery($filterId);
        
        if (empty($filterQuery['sql'])) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) as count FROM network_flows nf WHERE ' . $filterQuery['sql'];
        $result = $this->db->fetchOne($sql, $filterQuery['params']);
        
        return (int) $result['count'];
    }

    public function getStatsByDevice(int $deviceId, string $startDate, string $endDate): array
    {
        $sql = 'SELECT 
                    DATE(recv_ts) as date,
                    COUNT(*) as flow_count,
                    SUM(bytes_len) as total_bytes,
                    COUNT(DISTINCT local_ip) as unique_local_ips,
                    COUNT(DISTINCT other_ip) as unique_remote_ips
                FROM network_flows 
                WHERE device_id = ? AND recv_ts BETWEEN ? AND ?
                GROUP BY DATE(recv_ts)
                ORDER BY date';
        
        return $this->db->fetchAll($sql, [$deviceId, $startDate, $endDate]);
    }

    public function getTopApplications(int $deviceId, string $startDate, string $endDate, int $limit = 10): array
    {
        $sql = 'SELECT 
                    detected_application_name as application,
                    COUNT(*) as flow_count,
                    SUM(bytes_len) as total_bytes
                FROM network_flows 
                WHERE device_id = ? AND recv_ts BETWEEN ? AND ?
                    AND detected_application_name IS NOT NULL
                GROUP BY detected_application_name
                ORDER BY flow_count DESC
                LIMIT ?';
        
        return $this->db->fetchAll($sql, [$deviceId, $startDate, $endDate, $limit]);
    }

    public function getTopProtocols(int $deviceId, string $startDate, string $endDate, int $limit = 10): array
    {
        $sql = 'SELECT 
                    detected_protocol_name as protocol,
                    COUNT(*) as flow_count,
                    SUM(bytes_len) as total_bytes
                FROM network_flows 
                WHERE device_id = ? AND recv_ts BETWEEN ? AND ?
                    AND detected_protocol_name IS NOT NULL
                GROUP BY detected_protocol_name
                ORDER BY flow_count DESC
                LIMIT ?';
        
        return $this->db->fetchAll($sql, [$deviceId, $startDate, $endDate, $limit]);
    }

    public function getTopHosts(int $deviceId, string $startDate, string $endDate, int $limit = 10): array
    {
        $sql = 'SELECT 
                    host_server_name as host,
                    COUNT(*) as flow_count,
                    SUM(bytes_len) as total_bytes
                FROM network_flows 
                WHERE device_id = ? AND recv_ts BETWEEN ? AND ?
                    AND host_server_name IS NOT NULL
                GROUP BY host_server_name
                ORDER BY flow_count DESC
                LIMIT ?';
        
        return $this->db->fetchAll($sql, [$deviceId, $startDate, $endDate, $limit]);
    }

    public function getHourlyStats(int $deviceId, string $date): array
    {
        $sql = 'SELECT 
                    HOUR(recv_ts) as hour,
                    COUNT(*) as flow_count,
                    SUM(bytes_len) as total_bytes
                FROM network_flows 
                WHERE device_id = ? AND DATE(recv_ts) = ?
                GROUP BY HOUR(recv_ts)
                ORDER BY hour';
        
        return $this->db->fetchAll($sql, [$deviceId, $date]);
    }

    public function getUniqueApplications(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT detected_application_name as application 
             FROM network_flows 
             WHERE detected_application_name IS NOT NULL 
             ORDER BY detected_application_name'
        );
    }

    public function getUniqueProtocols(): array
    {
        return $this->db->fetchAll(
            'SELECT DISTINCT detected_protocol_name as protocol 
             FROM network_flows 
             WHERE detected_protocol_name IS NOT NULL 
             ORDER BY detected_protocol_name'
        );
    }

    public function getSSLInfo(int $flowId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM ssl_info WHERE flow_id = ?', [$flowId]);
    }

    public function getHTTPInfo(int $flowId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM http_info WHERE flow_id = ?', [$flowId]);
    }
}

