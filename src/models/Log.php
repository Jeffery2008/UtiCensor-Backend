<?php

namespace UtiCensor\Models;

use UtiCensor\Utils\Database;

class Log
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 创建日志记录
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO logs (
            level, type, message, context, user_id, session_id, request_id,
            ip_address, user_agent, request_method, request_url, request_headers,
            request_body, response_status, response_body, error_code, error_file,
            error_line, error_trace, execution_time, memory_usage
        ) VALUES (
            :level, :type, :message, :context, :user_id, :session_id, :request_id,
            :ip_address, :user_agent, :request_method, :request_url, :request_headers,
            :request_body, :response_status, :response_body, :error_code, :error_file,
            :error_line, :error_trace, :execution_time, :memory_usage
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'level' => $data['level'] ?? 'info',
            'type' => $data['type'] ?? null,
            'message' => $data['message'] ?? '',
            'context' => $data['context'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'request_method' => $data['request_method'] ?? null,
            'request_url' => $data['request_url'] ?? null,
            'request_headers' => $data['request_headers'] ?? null,
            'request_body' => $data['request_body'] ?? null,
            'response_status' => $data['response_status'] ?? null,
            'response_body' => $data['response_body'] ?? null,
            'error_code' => $data['error_code'] ?? null,
            'error_file' => $data['error_file'] ?? null,
            'error_line' => $data['error_line'] ?? null,
            'error_trace' => $data['error_trace'] ?? null,
            'execution_time' => $data['execution_time'] ?? null,
            'memory_usage' => $data['memory_usage'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * 查询日志记录
     */
    public function find(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM logs WHERE 1=1";
        $params = [];

        // 添加过滤条件
        if (!empty($filters['level'])) {
            $sql .= " AND level = :level";
            $params['level'] = $filters['level'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $sql .= " AND ip_address = :ip_address";
            $params['ip_address'] = $filters['ip_address'];
        }

        if (!empty($filters['request_id'])) {
            $sql .= " AND request_id = :request_id";
            $params['request_id'] = $filters['request_id'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (message LIKE :search OR context LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 统计日志数量
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM logs WHERE 1=1";
        $params = [];

        // 添加过滤条件
        if (!empty($filters['level'])) {
            $sql .= " AND level = :level";
            $params['level'] = $filters['level'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $sql .= " AND ip_address = :ip_address";
            $params['ip_address'] = $filters['ip_address'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (message LIKE :search OR context LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * 获取日志统计信息
     */
    public function getStats(): array
    {
        $stats = [];
        
        // 总日志数
        $sql = "SELECT COUNT(*) FROM logs";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['total_logs'] = (int)$stmt->fetchColumn();
        
        // 错误日志数
        $sql = "SELECT COUNT(*) FROM logs WHERE level = 'error'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['error_count'] = (int)$stmt->fetchColumn();
        
        // 警告日志数
        $sql = "SELECT COUNT(*) FROM logs WHERE level = 'warning'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['warning_count'] = (int)$stmt->fetchColumn();
        
        // 今日日志数
        $sql = "SELECT COUNT(*) FROM logs WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['today_count'] = (int)$stmt->fetchColumn();
        
        // 按级别分组的统计
        $sql = "SELECT 
                    level,
                    COUNT(*) as count
                FROM logs 
                GROUP BY level
                ORDER BY count DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stats['by_level'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return $stats;
    }

    /**
     * 清理过期日志
     */
    public function cleanup(int $days = 30): int
    {
        $sql = "DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }

    /**
     * 根据ID获取日志详情
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM logs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * 获取日志类型列表
     */
    public function getTypes(): array
    {
        $sql = "SELECT DISTINCT type FROM logs WHERE type IS NOT NULL ORDER BY type";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * 获取日志级别列表
     */
    public function getLevels(): array
    {
        $sql = "SELECT DISTINCT level FROM logs ORDER BY level";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }