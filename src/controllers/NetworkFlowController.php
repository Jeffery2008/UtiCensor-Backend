<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\NetworkFlow;
use UtiCensor\Models\RouterZone;
use UtiCensor\Models\Device;
use UtiCensor\Utils\Logger;
use UtiCensor\Utils\JWT;
use UtiCensor\Models\User;

class NetworkFlowController
{
    private $flowModel;
    private $zoneModel;
    private $deviceModel;
    private $userModel;

    public function __construct()
    {
        $this->flowModel = new NetworkFlow();
        $this->zoneModel = new RouterZone();
        $this->deviceModel = new Device();
        $this->userModel = new User();
    }

    /**
     * 获取网络流量列表
     */
    public function index()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 获取查询参数
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['device_id'])) {
                $filters['device_id'] = (int)$_GET['device_id'];
            }
            if (!empty($_GET['protocol'])) {
                $filters['protocol'] = $_GET['protocol'];
            }
            if (!empty($_GET['application'])) {
                $filters['application'] = $_GET['application'];
            }
            if (!empty($_GET['category'])) {
                $filters['category'] = $_GET['category'];
            }
            if (!empty($_GET['source_ip'])) {
                $filters['source_ip'] = $_GET['source_ip'];
            }
            if (!empty($_GET['destination_ip'])) {
                $filters['destination_ip'] = $_GET['destination_ip'];
            }
            if (!empty($_GET['is_blocked'])) {
                $filters['is_blocked'] = $_GET['is_blocked'] === 'true' ? 1 : 0;
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }

            // 查询流量数据
            $flows = $this->flowModel->find($filters, $limit, $offset);
            $total = $this->flowModel->count($filters);

            // 记录API请求
            Logger::apiRequestLog(
                'GET',
                $_SERVER['REQUEST_URI'],
                $_GET,
                ['count' => count($flows), 'total' => $total],
                200
            );

            echo json_encode([
                'success' => true,
                'data' => [
                'flows' => $flows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
                ]
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_network_flows']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取网络流量失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取流量统计信息
     */
    public function stats()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            $stats = $this->flowModel->getStats($filters);

            // 记录API请求
            Logger::apiRequestLog('GET', $_SERVER['REQUEST_URI'], $_GET, null, 200);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_flow_stats']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取流量统计失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取小时级流量统计
     */
    public function hourlyStats()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['device_id'])) {
                $filters['device_id'] = (int)$_GET['device_id'];
            }
            if (!empty($_GET['date'])) {
                $filters['date'] = $_GET['date'];
            }

            $stats = $this->flowModel->getHourlyStats($filters);

            // 记录API请求
            Logger::apiRequestLog('GET', $_SERVER['REQUEST_URI'], $_GET, null, 200);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_hourly_stats']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取小时级统计失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取路由器区域列表
     */
    public function zones()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            $zones = $this->zoneModel->findAll();

            echo json_encode([
                'success' => true,
                'data' => $zones
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_zones']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取路由器区域失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取设备列表
     */
    public function devices()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            $devices = $this->deviceModel->findAll();

            echo json_encode([
                'success' => true,
                'data' => $devices
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_devices']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取设备列表失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取热门目标IP
     */
        public function topDestinations()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $destinations = $this->flowModel->getTopDestinations($filters, $limit);

            echo json_encode([
                'success' => true,
                'data' => $destinations
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_top_destinations']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取热门目标IP失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取流量异常检测
     */
        public function anomalies()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            $anomalies = $this->flowModel->getAnomalies($filters);

            echo json_encode([
                'success' => true,
                'data' => $anomalies
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_anomalies']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取流量异常失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取协议分布
     */
        public function protocolDistribution()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            $distribution = $this->flowModel->getProtocolDistribution($filters);

            echo json_encode([
                'success' => true,
                'data' => $distribution
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_protocol_distribution']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取协议分布失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 清理过期流量数据
     */
    public function cleanup()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
            $deletedCount = $this->flowModel->cleanup($days);

            // 记录操作
            Logger::userAction("清理过期流量数据", [
                'user_id' => $user['id'],
                'days' => $days,
                'deleted_count' => $deletedCount
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'deleted_count' => $deletedCount,
                    'days' => $days
                ],
                'message' => "成功清理 {$deletedCount} 条过期流量记录"
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'cleanup_flows']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '清理流量数据失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 导出流量数据
     */
    public function export()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => '未授权']);
                return;
            }

            // 获取查询参数
            $filters = [];
            if (!empty($_GET['router_zone_id'])) {
                $filters['router_zone_id'] = (int)$_GET['router_zone_id'];
            }
            if (!empty($_GET['device_id'])) {
                $filters['device_id'] = (int)$_GET['device_id'];
            }
            if (!empty($_GET['protocol'])) {
                $filters['protocol'] = $_GET['protocol'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            // 查询所有符合条件的流量数据
            $flows = $this->flowModel->find($filters, 10000, 0); // 最多导出10000条

            // 设置CSV头
            header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="network_flows_' . date('Y-m-d_H-i-s') . '.csv"');

            // 输出CSV
        $output = fopen('php://output', 'w');
        
            // 写入BOM
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // 写入表头
            fputcsv($output, [
                'ID', '路由器区域', '设备名称', '源IP', '目标IP', '源端口', '目标端口',
                '协议', '发送字节', '接收字节', '发送包数', '接收包数', '连接开始时间',
                '连接结束时间', '持续时间', '应用', '类别', '是否被阻止', '阻止原因'
            ]);

            // 写入数据
        foreach ($flows as $flow) {
                fputcsv($output, [
                $flow['id'],
                    $flow['zone_name'] ?? '',
                    $flow['device_name'] ?? '',
                    $flow['source_ip'] ?? '',
                    $flow['destination_ip'] ?? '',
                    $flow['source_port'] ?? '',
                    $flow['destination_port'] ?? '',
                    $flow['protocol'] ?? '',
                    $flow['bytes_sent'] ?? 0,
                    $flow['bytes_received'] ?? 0,
                    $flow['packets_sent'] ?? 0,
                    $flow['packets_received'] ?? 0,
                    $flow['connection_start'] ?? '',
                    $flow['connection_end'] ?? '',
                    $flow['duration'] ?? 0,
                    $flow['application'] ?? '',
                    $flow['category'] ?? '',
                    $flow['is_blocked'] ? '是' : '否',
                    $flow['block_reason'] ?? ''
                ]);
        }

        fclose($output);

            // 记录操作
            Logger::userAction("导出流量数据", [
                'user_id' => $user['id'],
                'filters' => $filters,
                'exported_count' => count($flows)
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'export_flows']);
            
            http_response_code(500);
        echo json_encode([
                'error' => '导出流量数据失败',
                'message' => $e->getMessage()
            ]);
        }
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
}


            // 写入数据
        foreach ($flows as $flow) {
                fputcsv($output, [
                $flow['id'],
                    $flow['zone_name'] ?? '',
                    $flow['device_name'] ?? '',
                    $flow['source_ip'] ?? '',
                    $flow['destination_ip'] ?? '',
                    $flow['source_port'] ?? '',
                    $flow['destination_port'] ?? '',
                    $flow['protocol'] ?? '',
                    $flow['bytes_sent'] ?? 0,
                    $flow['bytes_received'] ?? 0,
                    $flow['packets_sent'] ?? 0,
                    $flow['packets_received'] ?? 0,
                    $flow['connection_start'] ?? '',
                    $flow['connection_end'] ?? '',
                    $flow['duration'] ?? 0,
                    $flow['application'] ?? '',
                    $flow['category'] ?? '',
                    $flow['is_blocked'] ? '是' : '否',
                    $flow['block_reason'] ?? ''
                ]);
        }

        fclose($output);

            // 记录操作
            Logger::userAction("导出流量数据", [
                'user_id' => $user['id'],
                'filters' => $filters,
                'exported_count' => count($flows)
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'export_flows']);
            
            http_response_code(500);
        echo json_encode([
                'error' => '导出流量数据失败',
                'message' => $e->getMessage()
            ]);
        }
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
}
