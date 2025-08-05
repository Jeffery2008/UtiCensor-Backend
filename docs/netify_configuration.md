# Netify 配置说明

## 概述

Netify 配置控制着系统如何处理来自路由器的网络流量数据，特别是如何处理未知设备和路由器区域的自动注册。

## 配置项说明

### 基础配置

- `listen_host`: 监听地址 (默认: 0.0.0.0)
- `listen_port`: 监听端口 (默认: 9000)
- `buffer_size`: 缓冲区大小 (默认: 8192)

### 安全设置

#### 设备处理

- `allow_unknown_devices`: 是否允许未知设备的数据写入数据库
  - `true`: 允许未知设备的数据写入，即使设备不存在
  - `false`: 只处理已知设备的数据

- `auto_create_devices`: 是否自动创建未知设备
  - `true`: 当检测到新设备时自动创建设备记录
  - `false`: 不自动创建设备，只处理已知设备

#### 路由器区域处理

- `allow_unknown_zones`: 是否允许未知路由器区域的数据写入数据库
  - `true`: 允许未知路由器区域的数据写入
  - `false`: 只处理已知路由器区域的数据

- `auto_create_zones`: 是否自动创建未知路由器区域
  - `true`: 当检测到新路由器时自动创建区域记录
  - `false`: 不自动创建区域，只处理已知区域

### 设备分配设置

- `auto_assign_devices_to_router`: 是否自动将新设备分配到发送请求的路由器区域
  - `true`: 新设备自动分配到对应的路由器区域
  - `false`: 新设备不自动分配区域

### 路由器标识符设置

- `prefer_router_identifier`: 优先使用路由器脚本发送的标识符
  - `true`: 优先使用路由器发送的标识符
  - `false`: 使用其他方式确定路由器标识

- `generate_dynamic_identifier`: 当没有标识符时，生成基于连接信息的动态标识符
  - `true`: 自动生成动态标识符
  - `false`: 使用默认标识符

- `fallback_to_ip_mapping`: 是否回退到IP映射（当路由器标识符不可用时）
  - `true`: 使用IP映射作为备用方案
  - `false`: 不使用IP映射

## 配置组合示例

### 示例1: 严格模式（推荐用于生产环境）

```php
'netify' => [
    'allow_unknown_devices' => false,
    'allow_unknown_zones' => false,
    'auto_create_devices' => false,
    'auto_create_zones' => false,
    'auto_assign_devices_to_router' => true,
    'prefer_router_identifier' => true,
    'generate_dynamic_identifier' => false,
    'fallback_to_ip_mapping' => false,
]
```

**行为**: 只处理已知设备和路由器区域的数据，不自动创建任何记录。

### 示例2: 自动模式（推荐用于测试环境）

```php
'netify' => [
    'allow_unknown_devices' => true,
    'allow_unknown_zones' => true,
    'auto_create_devices' => true,
    'auto_create_zones' => true,
    'auto_assign_devices_to_router' => true,
    'prefer_router_identifier' => true,
    'generate_dynamic_identifier' => true,
    'fallback_to_ip_mapping' => true,
]
```

**行为**: 自动创建所有未知设备和路由器区域，允许所有数据写入。

### 示例3: 混合模式（推荐用于开发环境）

```php
'netify' => [
    'allow_unknown_devices' => false,
    'allow_unknown_zones' => false,
    'auto_create_devices' => true,
    'auto_create_zones' => true,
    'auto_assign_devices_to_router' => true,
    'prefer_router_identifier' => true,
    'generate_dynamic_identifier' => true,
    'fallback_to_ip_mapping' => false,
]
```

**行为**: 自动创建未知设备和路由器区域，但不允许没有对应记录的数据写入。

## 处理逻辑

### 设备处理流程

1. 检查设备是否存在（通过MAC地址）
2. 如果设备存在：
   - 更新设备信息（如果需要）
   - 继续处理数据
3. 如果设备不存在：
   - 检查 `auto_create_devices` 配置
   - 如果启用：自动创建设备
   - 如果禁用：检查 `allow_unknown_devices` 配置
   - 如果允许：继续处理数据
   - 如果不允许：跳过数据

### 路由器区域处理流程

1. 检查路由器区域是否存在（通过路由器标识符）
2. 如果区域存在：
   - 使用现有区域
   - 继续处理数据
3. 如果区域不存在：
   - 检查 `auto_create_zones` 配置
   - 如果启用：自动创建区域
   - 如果禁用：检查 `allow_unknown_zones` 配置
   - 如果允许：继续处理数据
   - 如果不允许：跳过数据

## 配置测试

使用配置测试页面验证配置：

```
http://your-server/backend/public/test_config.php
```

该页面会显示：
- 当前配置状态
- 配置逻辑测试结果
- 配置建议
- 模拟数据流处理测试

## 注意事项

1. **数据安全**: 在生产环境中，建议使用严格模式以防止意外数据写入
2. **性能考虑**: 自动创建功能会增加数据库写入操作
3. **存储空间**: 自动创建可能导致数据库记录快速增长
4. **维护**: 定期清理不需要的自动创建记录

## 故障排除

### 常见问题

1. **数据丢失**: 检查 `allow_unknown_devices` 和 `allow_unknown_zones` 设置
2. **设备未创建**: 检查 `auto_create_devices` 设置
3. **区域未创建**: 检查 `auto_create_zones` 设置
4. **设备分配错误**: 检查 `auto_assign_devices_to_router` 设置

### 日志分析

查看服务器日志了解处理过程：

```bash
# 查看netify服务日志
tail -f /var/log/netify.log

# 查看系统日志
journalctl -u netify-ingest -f
``` 