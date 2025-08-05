<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;
use UtiCensor\Utils\Logger;

class NetworkFlow
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 创建网络流量记录
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO network_flows (
            router_zone_id, source_ip, destination_ip, source_port, destination_port,
            protocol, bytes_sent, bytes_received, packets_sent, packets_received,
            connection_start, connection_end, duration, application, category,
            device_id, user_id, is_blocked, block_reason, flow_hash
        ) VALUES (
            :router_zone_id, :source_ip, :destination_ip, :source_port, :destination_port,
            :protocol, :bytes_sent, :bytes_received, :packets_sent, :packets_received,
            :connection_start, :connection_end, :duration, :application, :category,
            :device_id, :user_id, :is_blocked, :block_reason, :flow_hash
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'router_zone_id' => $data['router_zone_id'] ?? null,
            'source_ip' => $data['source_ip'] ?? null,
            'destination_ip' => $data['destination_ip'] ?? null,
            'source_port' => $data['source_port'] ?? null,
            'destination_port' => $data['destination_port'] ?? null,
            'protocol' => $data['protocol'] ?? null,
            'bytes_sent' => $data['bytes_sent'] ?? 0,
            'bytes_received' => $data['bytes_received'] ?? 0,
            'packets_sent' => $data['packets_sent'] ?? 0,
            'packets_received' => $data['packets_received'] ?? 0,
            'connection_start' => $data['connection_start'] ?? null,
            'connection_end' => $data['connection_end'] ?? null,
            'duration' => $data['duration'] ?? 0,
            'application' => $data['application'] ?? null,
            'category' => $data['category'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'is_blocked' => $data['is_blocked'] ?? false,
            'block_reason' => $data['block_reason'] ?? null,
            'flow_hash' => $data['flow_hash'] ?? null
        ]);

        $flowId = $this->db->lastInsertId();
        
        Logger::flowProcessing("网络流量记录创建", [
            'flow_id' => $flowId,
            'source_ip' => $data['source_ip'] ?? null,
            'destination_ip' => $data['destination_ip'] ?? null,
            'protocol' => $data['protocol'] ?? null,
            'application' => $data['application'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'router_zone_id' => $data['router_zone_id'] ?? null
        ]);
        
        return $flowId;
    }

    /**
     * 查询网络流量记录
     */
    public function find(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT nf.*, rz.zone_name, d.device_name, d.mac_address 
                FROM network_flows nf 
                LEFT JOIN router_zones rz ON nf.router_zone_id = rz.id 
                LEFT JOIN devices d ON nf.device_id = d.id 
                WHERE 1=1";
        $params = [];

        // 添加过滤条件
        if (!empty($filters['router_zone_id'])) {
            $sql .= " AND nf.router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['device_id'])) {
            $sql .= " AND nf.device_id = :device_id";
            $params['device_id'] = $filters['device_id'];
        }

        if (!empty($filters['protocol'])) {
            $sql .= " AND nf.protocol = :protocol";
            $params['protocol'] = $filters['protocol'];
        }

        if (!empty($filters['application'])) {
            $sql .= " AND nf.application LIKE :application";
            $params['application'] = '%' . $filters['application'] . '%';
        }

        if (!empty($filters['category'])) {
            $sql .= " AND nf.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['source_ip'])) {
            $sql .= " AND nf.source_ip = :source_ip";
            $params['source_ip'] = $filters['source_ip'];
        }

        if (!empty($filters['destination_ip'])) {
            $sql .= " AND nf.destination_ip = :destination_ip";
            $params['destination_ip'] = $filters['destination_ip'];
        }

        if (!empty($filters['is_blocked'])) {
            $sql .= " AND nf.is_blocked = :is_blocked";
            $params['is_blocked'] = $filters['is_blocked'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND nf.connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND nf.connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (nf.application LIKE :search OR nf.category LIKE :search OR d.device_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY nf.connection_start DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 统计网络流量数量
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM network_flows nf 
                LEFT JOIN router_zones rz ON nf.router_zone_id = rz.id 
                LEFT JOIN devices d ON nf.device_id = d.id 
                WHERE 1=1";
        $params = [];

        // 添加过滤条件（与find方法相同的过滤逻辑）
        if (!empty($filters['router_zone_id'])) {
            $sql .= " AND nf.router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['device_id'])) {
            $sql .= " AND nf.device_id = :device_id";
            $params['device_id'] = $filters['device_id'];
        }

        if (!empty($filters['protocol'])) {
            $sql .= " AND nf.protocol = :protocol";
            $params['protocol'] = $filters['protocol'];
        }

        if (!empty($filters['application'])) {
            $sql .= " AND nf.application LIKE :application";
            $params['application'] = '%' . $filters['application'] . '%';
        }

        if (!empty($filters['category'])) {
            $sql .= " AND nf.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['source_ip'])) {
            $sql .= " AND nf.source_ip = :source_ip";
            $params['source_ip'] = $filters['source_ip'];
        }

        if (!empty($filters['destination_ip'])) {
            $sql .= " AND nf.destination_ip = :destination_ip";
            $params['destination_ip'] = $filters['destination_ip'];
        }

        if (!empty($filters['is_blocked'])) {
            $sql .= " AND nf.is_blocked = :is_blocked";
            $params['is_blocked'] = $filters['is_blocked'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND nf.connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND nf.connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (nf.application LIKE :search OR nf.category LIKE :search OR d.device_name LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * 获取流量统计信息
     */
    public function getStats(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        // 添加过滤条件
        if (!empty($filters['router_zone_id'])) {
            $whereClause .= " AND router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['start_date'])) {
            $whereClause .= " AND connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClause .= " AND connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        // 总体统计
        $totalStats = "SELECT 
                        COUNT(*) as total_flows,
                        SUM(bytes_sent) as total_bytes_sent,
                        SUM(bytes_received) as total_bytes_received,
                        SUM(packets_sent) as total_packets_sent,
                        SUM(packets_received) as total_packets_received,
                        SUM(duration) as total_duration,
                        COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_flows
                      FROM network_flows 
                      $whereClause";

        $stmt = $this->db->prepare($totalStats);
        $stmt->execute($params);
        $totalStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // 按协议统计
        $protocolStats = "SELECT 
                           protocol,
                           COUNT(*) as count,
                           SUM(bytes_sent) as bytes_sent,
                           SUM(bytes_received) as bytes_received,
                           AVG(duration) as avg_duration
                         FROM network_flows 
                         $whereClause 
                         GROUP BY protocol 
                         ORDER BY count DESC";

        $stmt = $this->db->prepare($protocolStats);
        $stmt->execute($params);
        $protocolStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 按应用统计
        $appStats = "SELECT 
                      application,
                      COUNT(*) as count,
                      SUM(bytes_sent) as bytes_sent,
                      SUM(bytes_received) as bytes_received,
                      AVG(duration) as avg_duration
                    FROM network_flows 
                    $whereClause 
                    AND application IS NOT NULL 
                    GROUP BY application 
                    ORDER BY count DESC 
                    LIMIT 20";

        $stmt = $this->db->prepare($appStats);
        $stmt->execute($params);
        $appStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 按类别统计
        $categoryStats = "SELECT 
                           category,
                           COUNT(*) as count,
                           SUM(bytes_sent) as bytes_sent,
                           SUM(bytes_received) as bytes_received
                         FROM network_flows 
                         $whereClause 
                         AND category IS NOT NULL 
                         GROUP BY category 
                         ORDER BY count DESC";

        $stmt = $this->db->prepare($categoryStats);
        $stmt->execute($params);
        $categoryStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 按路由器区域统计
        $zoneStats = "SELECT 
                       rz.zone_name,
                       COUNT(*) as count,
                       SUM(nf.bytes_sent) as bytes_sent,
                       SUM(nf.bytes_received) as bytes_received,
                       COUNT(CASE WHEN nf.is_blocked = 1 THEN 1 END) as blocked_flows
                     FROM network_flows nf 
                     LEFT JOIN router_zones rz ON nf.router_zone_id = rz.id 
                     $whereClause 
                     GROUP BY rz.id, rz.zone_name 
                     ORDER BY count DESC";

        $stmt = $this->db->prepare($zoneStats);
        $stmt->execute($params);
        $zoneStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 按设备统计
        $deviceStats = "SELECT 
                         d.device_name,
                         d.mac_address,
                         COUNT(*) as count,
                         SUM(nf.bytes_sent) as bytes_sent,
                         SUM(nf.bytes_received) as bytes_received,
                         COUNT(CASE WHEN nf.is_blocked = 1 THEN 1 END) as blocked_flows
                       FROM network_flows nf 
                       LEFT JOIN devices d ON nf.device_id = d.id 
                       $whereClause 
                       AND d.id IS NOT NULL 
                       GROUP BY d.id, d.device_name, d.mac_address 
                       ORDER BY count DESC 
                       LIMIT 20";

        $stmt = $this->db->prepare($deviceStats);
        $stmt->execute($params);
        $deviceStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 时间序列数据（按小时统计）
        $timeSeriesStats = "SELECT 
                             DATE_FORMAT(connection_start, '%Y-%m-%d %H:00:00') as hour,
                             COUNT(*) as count,
                             SUM(bytes_sent) as bytes_sent,
                             SUM(bytes_received) as bytes_received
                FROM network_flows 
                           $whereClause 
                           GROUP BY DATE_FORMAT(connection_start, '%Y-%m-%d %H:00:00') 
                           ORDER BY hour DESC 
                           LIMIT 24";

        $stmt = $this->db->prepare($timeSeriesStats);
        $stmt->execute($params);
        $timeSeriesStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total' => $totalStats,
            'protocols' => $protocolStats,
            'applications' => $appStats,
            'categories' => $categoryStats,
            'zones' => $zoneStats,
            'devices' => $deviceStats,
            'time_series' => $timeSeriesStats
        ];
    }

    /**
     * 获取小时级流量统计
     */
    public function getHourlyStats(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        // 添加过滤条件
        if (!empty($filters['device_id'])) {
            $whereClause .= " AND device_id = :device_id";
            $params['device_id'] = $filters['device_id'];
        }

        if (!empty($filters['date'])) {
            $whereClause .= " AND DATE(connection_start) = :date";
            $params['date'] = $filters['date'];
        }

        // 按小时统计流量
        $sql = "SELECT 
                  HOUR(connection_start) as hour,
                  COUNT(*) as flow_count,
                  SUM(bytes_sent) as bytes_sent,
                  SUM(bytes_received) as bytes_received,
                  SUM(packets_sent) as packets_sent,
                  SUM(packets_received) as packets_received,
                  AVG(duration) as avg_duration,
                  COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_count
                FROM network_flows 
                $whereClause 
                GROUP BY HOUR(connection_start) 
                ORDER BY hour ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $hourlyStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 填充缺失的小时数据
        $filledStats = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $found = false;
            foreach ($hourlyStats as $stat) {
                if ($stat['hour'] == $hour) {
                    $filledStats[] = $stat;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $filledStats[] = [
                    'hour' => $hour,
                    'flow_count' => 0,
                    'bytes_sent' => 0,
                    'bytes_received' => 0,
                    'packets_sent' => 0,
                    'packets_received' => 0,
                    'avg_duration' => 0,
                    'blocked_count' => 0
                ];
            }
        }

        return $filledStats;
    }

    /**
     * 获取热门目标IP
     */
    public function getTopDestinations(array $filters = [], int $limit = 20): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['router_zone_id'])) {
            $whereClause .= " AND router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['start_date'])) {
            $whereClause .= " AND connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClause .= " AND connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $sql = "SELECT 
                  destination_ip,
                  COUNT(*) as connection_count,
                  SUM(bytes_sent) as total_bytes_sent,
                  SUM(bytes_received) as total_bytes_received,
                  AVG(duration) as avg_duration,
                  COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_count
                FROM network_flows 
                $whereClause 
                AND destination_ip IS NOT NULL 
                GROUP BY destination_ip 
                ORDER BY connection_count DESC 
                LIMIT :limit";

        $params['limit'] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取流量异常检测
     */
    public function getAnomalies(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['router_zone_id'])) {
            $whereClause .= " AND router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['start_date'])) {
            $whereClause .= " AND connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClause .= " AND connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        // 检测异常流量（基于字节数）
        $sql = "SELECT 
                  nf.*,
                  rz.zone_name,
                  d.device_name,
                  d.mac_address,
                  'high_bandwidth' as anomaly_type,
                  '异常高带宽使用' as anomaly_description
                FROM network_flows nf 
                LEFT JOIN router_zones rz ON nf.router_zone_id = rz.id 
                LEFT JOIN devices d ON nf.device_id = d.id 
                $whereClause 
                AND (nf.bytes_sent + nf.bytes_received) > (
                  SELECT AVG(bytes_sent + bytes_received) * 3 
                FROM network_flows 
                  $whereClause
                )
                ORDER BY (nf.bytes_sent + nf.bytes_received) DESC 
                LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取协议分布
     */
    public function getProtocolDistribution(array $filters = []): array
    {
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($filters['router_zone_id'])) {
            $whereClause .= " AND router_zone_id = :router_zone_id";
            $params['router_zone_id'] = $filters['router_zone_id'];
        }

        if (!empty($filters['start_date'])) {
            $whereClause .= " AND connection_start >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClause .= " AND connection_end <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        $sql = "SELECT 
                  protocol,
                  COUNT(*) as count,
                  SUM(bytes_sent + bytes_received) as total_bytes,
                  AVG(duration) as avg_duration,
                  COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_count
                FROM network_flows 
                $whereClause 
                AND protocol IS NOT NULL 
                GROUP BY protocol 
                ORDER BY count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 清理过期流量数据
     */
    public function cleanup(int $days = 30): int
    {
        $sql = "DELETE FROM network_flows WHERE connection_start < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }
}

