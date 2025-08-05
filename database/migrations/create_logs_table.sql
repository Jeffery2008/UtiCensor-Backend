-- 创建日志表
CREATE TABLE IF NOT EXISTS `logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` varchar(10) NOT NULL COMMENT '日志级别: debug, info, warning, error',
  `type` varchar(50) DEFAULT NULL COMMENT '日志类型: device_assignment, zone_creation, flow_processing, connection_event, api_request, system_error, etc',
  `message` text NOT NULL COMMENT '日志消息',
  `context` text COMMENT '上下文信息',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `session_id` varchar(255) DEFAULT NULL COMMENT '会话ID',
  `request_id` varchar(255) DEFAULT NULL COMMENT '请求ID',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `request_method` varchar(10) DEFAULT NULL COMMENT '请求方法: GET, POST, PUT, DELETE',
  `request_url` text COMMENT '请求URL',
  `request_headers` text COMMENT '请求头信息',
  `request_body` text COMMENT '请求体数据',
  `response_status` int(11) DEFAULT NULL COMMENT '响应状态码',
  `response_body` text COMMENT '响应体数据',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误代码',
  `error_file` varchar(500) DEFAULT NULL COMMENT '错误文件',
  `error_line` int(11) DEFAULT NULL COMMENT '错误行号',
  `error_trace` text COMMENT '错误堆栈',
  `execution_time` decimal(10,4) DEFAULT NULL COMMENT '执行时间(秒)',
  `memory_usage` bigint(20) DEFAULT NULL COMMENT '内存使用量(字节)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_type` (`type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_level_created_at` (`level`, `created_at`),
  KEY `idx_type_created_at` (`type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统日志表';

-- 创建日志配置表
CREATE TABLE IF NOT EXISTS `log_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL COMMENT '配置键',
  `config_value` text NOT NULL COMMENT '配置值',
  `description` varchar(500) DEFAULT NULL COMMENT '配置描述',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日志配置表';

-- 插入默认日志配置
INSERT INTO `log_configs` (`config_key`, `config_value`, `description`) VALUES
('log_level', 'info', '全局日志级别: debug, info, warning, error, none'),
('log_device_assignments', 'true', '是否记录设备分配日志'),
('log_zone_creation', 'true', '是否记录区域创建日志'),
('log_flow_processing', 'false', '是否记录流量处理日志'),
('log_connection_events', 'true', '是否记录连接事件'),
('log_api_requests', 'true', '是否记录API请求日志'),
('log_system_errors', 'true', '是否记录系统错误'),
('log_user_actions', 'true', '是否记录用户操作'),
('log_retention_days', '30', '日志保留天数'),
('log_max_file_size', '10485760', '日志文件最大大小(字节)'); 