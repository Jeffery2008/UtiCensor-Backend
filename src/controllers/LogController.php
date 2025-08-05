<?php

namespace UtiCensor\Controllers;

use UtiCensor\Models\Log;
use UtiCensor\Models\User;
use UtiCensor\Utils\Logger;
use UtiCensor\Utils\JWT;

class LogController
{
    private $logModel;
    private $userModel;

    public function __construct()
    {
        $this->logModel = new Log();
        $this->userModel = new User();
    }

    /**
     * 获取日志列表
     */
    public function index()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            // 获取查询参数
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;

            // 构建过滤条件
            $filters = [];
            if (!empty($_GET['level'])) {
                $filters['level'] = $_GET['level'];
            }
            if (!empty($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            if (!empty($_GET['user_id'])) {
                $filters['user_id'] = (int)$_GET['user_id'];
            }
            if (!empty($_GET['ip_address'])) {
                $filters['ip_address'] = $_GET['ip_address'];
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

            // 查询日志
            $logs = $this->logModel->find($filters, $limit, $offset);
            $total = $this->logModel->count($filters);

            // 记录API请求
            Logger::apiRequestLog(
                'GET',
                $_SERVER['REQUEST_URI'],
                $_GET,
                ['count' => count($logs), 'total' => $total],
                200
            );

            echo json_encode([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_logs']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取日志失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取日志详情
     */
    public function show($id)
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            $log = $this->logModel->findById($id);
            if (!$log) {
                http_response_code(404);
                echo json_encode(['error' => '日志不存在']);
                return;
            }

            // 记录API请求
            Logger::apiRequestLog('GET', $_SERVER['REQUEST_URI'], null, null, 200);

            echo json_encode([
                'success' => true,
                'data' => $log
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_log_detail', 'log_id' => $id]);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取日志详情失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取日志统计信息
     */
    public function stats()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            $stats = $this->logModel->getStats();

            // 记录API请求
            Logger::apiRequestLog('GET', $_SERVER['REQUEST_URI'], null, null, 200);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_log_stats']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取日志统计失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取日志类型列表
     */
    public function types()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            $types = $this->logModel->getTypes();

            echo json_encode([
                'success' => true,
                'data' => $types
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_log_types']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取日志类型失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取日志级别列表
     */
    public function levels()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            $levels = $this->logModel->getLevels();

            echo json_encode([
                'success' => true,
                'data' => $levels
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'get_log_levels']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '获取日志级别失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 清理过期日志
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
            $deletedCount = $this->logModel->cleanup($days);

            // 记录操作
            Logger::userAction("清理过期日志", [
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
                'message' => "成功清理 {$deletedCount} 条过期日志"
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'cleanup_logs']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '清理日志失败',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * 导出日志
     */
    public function export()
    {
        try {
            // 验证权限
            $user = $this->getCurrentUser();
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => '权限不足']);
                return;
            }

            // 获取查询参数
            $filters = [];
            if (!empty($_GET['level'])) {
                $filters['level'] = $_GET['level'];
            }
            if (!empty($_GET['type'])) {
                $filters['type'] = $_GET['type'];
            }
            if (!empty($_GET['start_date'])) {
                $filters['start_date'] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date'])) {
                $filters['end_date'] = $_GET['end_date'];
            }

            // 查询所有符合条件的日志
            $logs = $this->logModel->find($filters, 10000, 0); // 最多导出10000条

            // 设置CSV头
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.csv"');

            // 输出CSV
            $output = fopen('php://output', 'w');
            
            // 写入BOM
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // 写入表头
            fputcsv($output, [
                'ID', '级别', '类型', '消息', '用户ID', 'IP地址', '请求方法', 
                '请求URL', '错误代码', '错误文件', '错误行号', '创建时间'
            ]);

            // 写入数据
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['level'],
                    $log['type'],
                    $log['message'],
                    $log['user_id'],
                    $log['ip_address'],
                    $log['request_method'],
                    $log['request_url'],
                    $log['error_code'],
                    $log['error_file'],
                    $log['error_line'],
                    $log['created_at']
                ]);
            }

            fclose($output);

            // 记录操作
            Logger::userAction("导出日志", [
                'user_id' => $user['id'],
                'filters' => $filters,
                'exported_count' => count($logs)
            ]);

        } catch (\Throwable $e) {
            Logger::exception($e, 'api_error', ['action' => 'export_logs']);
            
            http_response_code(500);
            echo json_encode([
                'error' => '导出日志失败',
                'message' => $e->getMessage()
            ]);
        }
    }