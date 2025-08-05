# 基于路由器标识符的自动区域划分

## 概述

为了解决公网IP不固定和接口重名的问题，系统现在支持直接使用路由器发送的标识符来自动划分局域网区域。这种方式更加可靠和灵活，不依赖于IP地址映射。

## 工作原理

### 1. 优先级顺序
1. **路由器脚本标识符** (最高优先级)
   - 路由器脚本直接发送唯一标识符
   - 最可靠的方式，推荐使用

2. **IP映射配置** (备用方案)
   - 当路由器脚本未发送标识符时使用
   - 基于IP地址的映射配置

3. **动态标识符生成** (自动方案)
   - 当没有标识符和IP映射时自动生成
   - 基于IP地址和接口信息的哈希值

### 2. 自动区域创建
- 系统自动为每个唯一的路由器标识符创建对应的区域
- 区域名称根据标识符类型自动生成
- 支持动态区域命名和描述

### 3. 设备自动分配
- 新设备自动分配到发送请求的路由器区域
- 避免设备出现在"未知区域"
- 支持设备重新分配

## 配置选项

### 主要配置
```php
'netify' => [
    // 路由器标识符设置
    'prefer_router_identifier' => true,  // 优先使用路由器脚本发送的标识符
    'generate_dynamic_identifier' => true,  // 当没有标识符时，生成基于连接信息的动态标识符
    'fallback_to_ip_mapping' => false,  // 是否回退到IP映射（当路由器标识符不可用时）
    
    // 自动创建设置
    'auto_create_zones' => true,      // 自动创建路由器区域
    'auto_assign_devices_to_router' => true,  // 自动分配设备到路由器
],
```

### 备用IP映射配置
```php
'router_identifier_mapping' => [
    // 仅在路由器脚本未发送标识符时使用
    // '192.168.1.1' => 'router_aabbccddeeff',
    // '192.168.2.1' => 'router_112233445566',
],
```

## 路由器脚本配置

### 推荐方式：发送路由器标识符
在路由器脚本中直接发送唯一标识符：

```bash
# 在 netify-push-advanced 脚本中
ROUTER_IDENTIFIER=$(get_router_identifier)

# 发送数据时包含路由器标识符（使用HTTP头）
curl -X POST "http://your-server:9000" \
  -H "Content-Type: application/json" \
  -H "X-Router-Identifier: $ROUTER_IDENTIFIER" \
  -d "$json_data"

# 或者使用 Router-Identifier 头（兼容格式）
curl -X POST "http://your-server:9000" \
  -H "Content-Type: application/json" \
  -H "Router-Identifier: $ROUTER_IDENTIFIER" \
  -d "$json_data"
```

### 路由器标识符生成函数
```bash
get_router_identifier() {
    # 基于WAN MAC地址生成唯一标识符
    WAN_MAC=$(ip link show dev eth0 | grep -o -E '([[:xdigit:]]{2}:){5}[[:xdigit:]]{2}' | head -1)
    if [ -n "$WAN_MAC" ]; then
        echo "router_$(echo $WAN_MAC | tr -d ':')"
    else
        # 备用方案：基于设备序列号
        SERIAL=$(cat /proc/cmdline | grep -o 'serial=[^ ]*' | cut -d= -f2)
        if [ -n "$SERIAL" ]; then
            echo "router_$SERIAL"
        else
            # 最后备用：基于主机名和时间戳
            echo "router_$(hostname)_$(date +%s)"
        fi
    fi
}
```

## 工作流程

### 1. 路由器发送数据
```
路由器 → 生成唯一标识符 → 发送数据到服务器
```

### 2. 服务器处理
```
接收数据 → 提取路由器标识符 → 查找/创建区域 → 分配设备
```

### 3. 区域管理
```
标识符存在 → 使用现有区域
标识符不存在 → 自动创建新区域
```

## 优势

### 1. 不依赖IP地址
- ✅ 公网IP变化不影响区域划分
- ✅ 支持动态IP环境
- ✅ 避免IP冲突问题

### 2. 处理接口重名
- ✅ 相同接口名不会冲突
- ✅ 基于路由器标识符区分
- ✅ 支持多路由器相同配置

### 3. 自动化和灵活性
- ✅ 自动创建路由器区域
- ✅ 自动分配设备到区域
- ✅ 支持动态标识符生成

### 4. 向后兼容
- ✅ 支持现有的IP映射配置
- ✅ 渐进式迁移
- ✅ 多种标识符来源

## 使用示例

### 场景1：路由器脚本发送标识符
```
路由器标识符: router_56d5dc819521
结果: 创建区域 "动态路由器区域 - router_56d5dc819521"
设备: 自动分配到该区域
```

### 场景2：无标识符，有IP映射
```
IP地址: 192.168.1.1
IP映射: '192.168.1.1' => 'router_office_001'
结果: 使用映射的标识符 "router_office_001"
```

### 场景3：无标识符，无IP映射
```
IP地址: 10.0.0.1
接口: eth0
结果: 生成动态标识符 "router_a1b2c3d4e5f6"
```

## 测试和验证

### 运行测试脚本
```bash
cd backend
php scripts/test_router_identifier_auto_zone.php
```

### 运行HTTP客户端测试
```bash
cd backend
php scripts/test_http_client.php
```

### 验证区域创建
```bash
cd backend
php scripts/test_router_overview.php
```

### 检查设备分配
```bash
cd backend
php scripts/test_device_assignment.php
```

## 故障排除

### 路由器标识符问题
1. **标识符重复**
   - 检查路由器脚本的标识符生成逻辑
   - 确保基于唯一特征生成标识符

2. **标识符未发送**
   - 检查路由器脚本的HTTP头设置
   - 验证网络连接和防火墙设置

3. **动态标识符不稳定**
   - 检查IP地址和接口信息
   - 考虑使用更稳定的标识符生成方式

### 区域创建问题
1. **区域创建失败**
   - 检查数据库连接和权限
   - 验证自动创建配置设置

2. **区域名称混乱**
   - 检查标识符格式和长度
   - 调整区域命名规则

### 设备分配问题
1. **设备未分配到区域**
   - 检查设备自动分配配置
   - 验证路由器区域状态

2. **设备分配到错误区域**
   - 检查路由器标识符是否正确
   - 验证设备MAC地址信息

## 最佳实践

### 1. 路由器标识符设计
- 使用基于硬件特征的标识符（MAC地址、序列号）
- 避免使用可能变化的标识符（IP地址、主机名）
- 确保标识符的唯一性和稳定性

### 2. 配置管理
- 优先使用路由器脚本发送标识符
- 保留IP映射作为备用方案
- 定期检查和更新配置

### 3. 监控和维护
- 定期检查路由器区域状态
- 监控设备分配情况
- 及时处理异常情况

## 迁移指南

### 从IP映射迁移到路由器标识符
1. 更新路由器脚本，添加标识符发送功能
2. 测试新的标识符生成和发送
3. 验证区域自动创建功能
4. 逐步减少IP映射配置
5. 监控系统运行状态

### 配置示例
```php
// 旧配置（基于IP映射）
'router_identifier_mapping' => [
    '192.168.1.1' => 'router_office_001',
    '192.168.2.1' => 'router_home_001',
],

// 新配置（基于路由器标识符）
'netify' => [
    'prefer_router_identifier' => true,
    'generate_dynamic_identifier' => true,
    'fallback_to_ip_mapping' => true,  // 迁移期间保持兼容
],
```

## 相关文件

- `src/Services/NetifyIngestService.php` - 主要处理逻辑
- `config/app.php` - 配置文件
- `scripts/test_router_identifier_auto_zone.php` - 测试脚本
- `docs/device_auto_assignment.md` - 设备自动分配文档 