<?php

namespace UtiCensor\Utils;

use UtiCensor\Models\Log;

class Logger
{
    private static $config = null;
    private static $logModel = null;
    private static $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'none' => 4
    ];

    private static function getConfig()
    {
        if (self::$config === null) {
            self::$config = require __DIR__ . '/../../config/app.php';
        }
        return self::$config;
    }

    private static function getLogModel()
    {
        if (self::$logModel === null) {
            self::$logModel = new Log();
        }
        return self::$logModel;
    }

    private static function shouldLog($level, $type = null)
    {
        $config = self::getConfig();
        $netifyConfig = $config['netify'];
        
        // 检查全局日志级别
        $currentLevel = $netifyConfig['log_level'] ?? 'info';
        $currentLevelNum = self::$logLevels[$currentLevel] ?? 1;
        $requestedLevelNum = self::$logLevels[$level] ?? 1;
        
        if ($requestedLevelNum < $currentLevelNum) {
            return false;
        }
        
        // 检查特定类型的日志开关
        if ($type === 'device_assignment' && !($netifyConfig['log_device_assignments'] ?? true)) {
            return false;
        }
        
        if ($type === 'zone_creation' && !($netifyConfig['log_zone_creation'] ?? true)) {
            return false;
        }
        
        if ($type === 'flow_processing' && !($netifyConfig['log_flow_processing'] ?? false)) {
            return false;
        }
        
        if ($type === 'connection_event' && !($netifyConfig['log_connection_events'] ?? true)) {
            return false;
        }
        
        return true;
    }

    private static function shouldOutputToConsole()
    {
        // 在CLI模式下总是输出
        if (php_sapi_name() === 'cli') {
            return true;
        }
        
        // 在Web模式下，根据配置决定是否输出
        $config = self::getConfig();
        $netifyConfig = $config['netify'];
        
        return $netifyConfig['log_console_output'] ?? false;
    }

    private static function getRequestData()
    {
        $data = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'request_url' => $_SERVER['REQUEST_URI'] ?? null,
            'request_headers' => self::formatHeaders($_SERVER),
            'request_body' => self::getRequestBody(),
            'session_id' => session_id() ?: null,
            'request_id' => uniqid('req_', true),
            'memory_usage' => memory_get_usage(true),
        ];

        // 获取用户ID（如果已登录）
        if (isset($_SESSION['user_id'])) {
            $data['user_id'] = $_SESSION['user_id'];
        }

        return $data;
    }

    private static function formatHeaders($server)
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', '-', strtolower($headerName));
                $headers[$headerName] = $value;
            }
        }
        return json_encode($headers);
    }

    private static function getRequestBody()
    {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            return $input;
        }

        if (!empty($_POST)) {
            return json_encode($_POST);
        }

        if (!empty($_GET)) {
            return json_encode($_GET);
        }

        return null;
    }

    private static function logToDatabase($level, $message, $type = null, $context = null, $errorData = null)
    {
        try {
            $logModel = self::getLogModel();
            $requestData = self::getRequestData();

            $logData = array_merge($requestData, [
                'level' => $level,
                'type' => $type,
                'message' => $message,
                'context' => $context ? json_encode($context) : null,
            ]);

            // 添加错误信息
            if ($errorData) {
                $logData['error_code'] = $errorData['code'] ?? null;
                $logData['error_file'] = $errorData['file'] ?? null;
                $logData['error_line'] = $errorData['line'] ?? null;
                $logData['error_trace'] = $errorData['trace'] ?? null;
            }

            $logModel->create($logData);
        } catch (\Exception $e) {
            // 如果数据库记录失败，至少输出到控制台
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }

    public static function debug($message, $type = null, $context = null)
    {
        if (self::shouldLog('debug', $type)) {
            // 检查是否应该输出到控制台
            if (self::shouldOutputToConsole()) {
                echo date('c') . " [DEBUG] " . $message . "\n";
            }
            self::logToDatabase('debug', $message, $type, $context);
        }
    }

    public static function info($message, $type = null, $context = null)
    {
        if (self::shouldLog('info', $type)) {
            // 检查是否应该输出到控制台
            if (self::shouldOutputToConsole()) {
                echo date('c') . " [INFO] " . $message . "\n";
            }
            self::logToDatabase('info', $message, $type, $context);
        }
    }

    public static function warning($message, $type = null, $context = null)
    {
        if (self::shouldLog('warning', $type)) {
            // 检查是否应该输出到控制台
            if (self::shouldOutputToConsole()) {
                echo date('c') . " [WARNING] " . $message . "\n";
            }
            self::logToDatabase('warning', $message, $type, $context);
        }
    }

    public static function error($message, $type = null, $context = null, $errorData = null)
    {
        if (self::shouldLog('error', $type)) {
            // 检查是否应该输出到控制台
            if (self::shouldOutputToConsole()) {
                echo date('c') . " [ERROR] " . $message . "\n";
            }
            self::logToDatabase('error', $message, $type, $context, $errorData);
        }
    }

    public static function deviceAssignment($message, $context = null)
    {
        self::info($message, 'device_assignment', $context);
    }

    public static function zoneCreation($message, $context = null)
    {
        self::info($message, 'zone_creation', $context);
    }

    public static function flowProcessing($message, $context = null)
    {
        self::debug($message, 'flow_processing', $context);
    }

    public static function connectionEvent($message, $context = null)
    {
        self::info($message, 'connection_event', $context);
    }

    public static function apiRequest($message, $context = null)
    {
        self::info($message, 'api_request', $context);
    }

    public static function systemError($message, $context = null, $errorData = null)
    {
        self::error($message, 'system_error', $context, $errorData);
    }

    public static function userAction($message, $context = null)
    {
        self::info($message, 'user_action', $context);
    }

    /**
     * 记录异常
     */
    public static function exception(\Throwable $exception, $type = null, $context = null)
    {
        $errorData = [
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        self::error($exception->getMessage(), $type ?: 'exception', $context, $errorData);
    }

    /**
     * 记录API请求
     */
    public static function apiRequestLog($method, $url, $requestData = null, $responseData = null, $statusCode = null, $executionTime = null)
    {
        $context = [
            'method' => $method,
            'url' => $url,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status_code' => $statusCode,
            'execution_time' => $executionTime
        ];

        $message = "API Request: {$method} {$url}";
        if ($statusCode) {
            $message .= " (Status: {$statusCode})";
        }
        if ($executionTime) {
            $message .= " (Time: {$executionTime}s)";
        }

        self::apiRequest($message, $context);
    }
} 