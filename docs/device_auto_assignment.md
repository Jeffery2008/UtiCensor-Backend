# 设备自动分配功能

## 概述

当检测到新的网络设备时，系统会自动将其分配到发送请求的路由器所属的区域下。这个功能确保了设备能够正确地归属到相应的路由器区域，避免了设备出现在"未知区域"的问题。

## 工作原理

### 1. 路由器标识符识别
- 系统首先根据连接信息识别路由器标识符
- 优先使用 `router_identifier_mapping` 配置
- 回退到 `router_mapping` 配置
- 如果都没有配置，使用默认标识符 "default"

### 2. 路由器区域确定
- 根据路由器标识符查找对应的路由器区域
- 如果区域不存在且启用了自动创建，则创建新的路由器区域
- 如果无法确定区域，使用默认区域

### 3. 设备自动分配
- 检测到新设备时，自动将其分配到当前路由器区域
- 如果设备已存在但没有区域分配，会重新分配到当前路由器区域
- 如果没有路由器区域信息，分配到默认区域

## 配置选项

在 `config/app.php` 中配置：

```php
'netify' => [
    // 基本设置
    'listen_host' => '0.0.0.0',
    'listen_port' => 9000,
    'buffer_size' => 8192,
    
    // 安全设置
    'allow_unknown_devices' => true,      // 允许未知设备
    'allow_unknown_zones' => true,        // 允许未知区域
    'auto_create_devices' => true,        // 自动创建设备
    'auto_create_zones' => true,          // 自动创建区域
    
    // 设备分配设置
    'auto_assign_devices_to_router' => true,  // 自动分配设备到路由器
],
```

## 路由器映射配置

### 路由器标识符映射（推荐）
```php
'router_identifier_mapping' => [
    '192.168.1.1' => 'router_aabbccddeeff',  // 基于WAN MAC地址
    '192.168.2.1' => 'router_112233445566',  // 基于WLAN MAC地址
    '10.0.0.1' => 'router_office_001',       // 基于自定义标识符
],
```

### 路由器映射（兼容性）
```php
'router_mapping' => [
    '192.168.1.1' => 'router_office_1',
    '192.168.2.1' => 'router_home_1',
],
```

## 使用流程

1. **路由器发送数据**
   - 路由器通过 `netify-push-advanced` 脚本发送网络流量数据
   - 脚本会自动生成路由器标识符并发送给服务器

2. **服务器处理**
   - 服务器接收数据并识别路由器标识符
   - 确定对应的路由器区域
   - 检测新设备并自动分配

3. **设备管理**
   - 新设备自动出现在对应的路由器区域下
   - 可以在前端界面查看设备分布情况

## 测试

### 运行测试脚本
```bash
cd backend
php scripts/test_device_assignment.php
```

### 清理测试数据
```bash
cd backend
php scripts/cleanup_test_data.php
```

## 日志输出

系统会在处理过程中输出详细的日志信息：

```
2024-01-01T12:00:00+08:00 Auto-created router zone: router_56d5dc819521 (ID: 5)
2024-01-01T12:00:01+08:00 Created new device 'Device-56d5dc81' and assigned to router zone ID: 5
2024-01-01T12:00:02+08:00 Auto-assigned device 'Device-56d5dc81' to router zone: router_56d5dc819521
```

## 故障排除

### 设备仍然出现在"未知区域"
1. 检查路由器映射配置是否正确
2. 确认路由器脚本发送的标识符
3. 运行测试脚本验证配置

### 路由器标识符显示为"default"
1. 检查 `router_identifier_mapping` 配置
2. 确认路由器IP地址是否正确
3. 验证路由器脚本的 `get_router_identifier()` 函数

### 自动创建区域失败
1. 检查数据库连接
2. 确认用户权限
3. 查看错误日志

## 最佳实践

1. **配置路由器映射**：为每个路由器配置明确的标识符映射
2. **使用有意义的标识符**：使用基于MAC地址或序列号的标识符
3. **定期检查**：定期查看设备分配情况，确保正确性
4. **备份配置**：备份路由器映射配置，避免丢失

## 相关文件

- `src/Services/NetifyIngestService.php` - 主要处理逻辑
- `src/models/Device.php` - 设备模型
- `src/models/RouterZone.php` - 路由器区域模型
- `config/app.php` - 配置文件
- `scripts/test_device_assignment.php` - 测试脚本
- `scripts/cleanup_test_data.php` - 清理脚本 