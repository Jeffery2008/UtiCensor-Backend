# 日志配置说明

## 概述

为了优化性能并减少不必要的CLI输出，系统提供了灵活的日志配置选项。在高流量环境下，频繁的日志输出可能会显著影响性能。

## 性能影响分析

### CLI输出的性能影响

1. **I/O阻塞**: `echo` 和 `print` 是同步I/O操作，会阻塞程序执行
2. **内存消耗**: 大量字符串输出会增加内存使用
3. **CPU开销**: 字符串格式化和时间戳生成消耗CPU资源
4. **磁盘I/O**: 如果输出重定向到文件，会增加磁盘写入负担

### 性能测试结果

在典型配置下，1000次日志输出大约需要：
- **启用所有日志**: 500-1000毫秒
- **仅启用错误日志**: 50-100毫秒
- **禁用所有日志**: 5-10毫秒

## 配置选项

### 全局日志级别

```php
'log_level' => 'info', // debug, info, warning, error, none
```

- `debug`: 输出所有日志（性能影响最大）
- `info`: 输出信息、警告和错误日志
- `warning`: 仅输出警告和错误日志
- `error`: 仅输出错误日志
- `none`: 禁用所有日志输出（性能最佳）

### 特定类型日志控制

```php
'log_device_assignments' => true,    // 设备分配日志
'log_zone_creation' => true,         // 区域创建日志
'log_flow_processing' => false,      // 流量处理日志
'log_connection_events' => true,     // 连接事件日志
```

## 配置示例

### 生产环境配置（高性能）

```php
'netify' => [
    'log_level' => 'warning',
    'log_device_assignments' => false,
    'log_zone_creation' => false,
    'log_flow_processing' => false,
    'log_connection_events' => true,
]
```

**特点**: 只记录重要的警告和错误，适合高流量生产环境。

### 开发环境配置（调试友好）

```php
'netify' => [
    'log_level' => 'info',
    'log_device_assignments' => true,
    'log_zone_creation' => true,
    'log_flow_processing' => false,
    'log_connection_events' => true,
]
```

**特点**: 记录设备分配和区域创建信息，便于调试。

### 调试环境配置（详细信息）

```php
'netify' => [
    'log_level' => 'debug',
    'log_device_assignments' => true,
    'log_zone_creation' => true,
    'log_flow_processing' => true,
    'log_connection_events' => true,
]
```

**特点**: 输出所有详细信息，适合问题排查。

## 环境变量配置

可以通过环境变量动态控制日志配置：

```bash
# 设置日志级别
export NETIFY_LOG_LEVEL=warning

# 控制特定类型日志
export NETIFY_LOG_DEVICE_ASSIGNMENTS=false
export NETIFY_LOG_ZONE_CREATION=false
export NETIFY_LOG_FLOW_PROCESSING=false
export NETIFY_LOG_CONNECTION_EVENTS=true
```

## 性能优化建议

### 1. 生产环境优化

- 设置 `log_level` 为 `warning` 或 `error`
- 禁用 `log_flow_processing`（流量处理日志）
- 根据需要禁用 `log_device_assignments` 和 `log_zone_creation`

### 2. 高流量环境优化

- 设置 `log_level` 为 `error`
- 禁用所有特定类型日志
- 考虑使用外部日志系统（如syslog）

### 3. 调试时优化

- 临时启用需要的日志类型
- 使用 `log_level` 为 `debug` 进行详细排查
- 调试完成后恢复生产配置

## 测试工具

### 日志配置测试

访问以下页面测试日志配置：

```
http://your-server/backend/public/test_logging.php
```

该页面提供：
- 当前配置状态显示
- 日志输出测试
- 性能测试
- 配置建议

### 性能测试

使用测试页面进行性能测试：

1. 点击"运行性能测试"按钮
2. 系统会执行1000次日志输出
3. 显示总耗时和平均耗时
4. 提供性能评估建议

## 监控和故障排除

### 日志监控

1. **连接事件**: 监控路由器连接状态
2. **设备分配**: 监控设备自动分配过程
3. **区域创建**: 监控路由器区域创建
4. **错误日志**: 监控系统错误和异常

### 常见问题

1. **日志输出过多**: 降低日志级别或禁用特定类型日志
2. **性能下降**: 检查是否启用了 `log_flow_processing`
3. **内存使用过高**: 考虑禁用不必要的日志输出

### 故障排除步骤

1. 检查当前日志配置
2. 运行性能测试
3. 根据建议调整配置
4. 监控系统性能变化

## 最佳实践

1. **开发阶段**: 使用 `debug` 级别进行详细调试
2. **测试阶段**: 使用 `info` 级别验证功能
3. **生产阶段**: 使用 `warning` 或 `error` 级别
4. **高流量环境**: 使用 `error` 级别或禁用所有日志
5. **定期检查**: 定期运行性能测试确保配置合理 