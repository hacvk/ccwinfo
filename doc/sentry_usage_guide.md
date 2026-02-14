# Sentry 日志系统使用指南

## 问题修复

✅ 已修复 `Call to undefined function env()` 错误
- 原因：ThinkPHP 5 使用 `Env::get()` 而不是 Laravel 风格的 `env()` 函数
- 解决：已将配置文件中所有 `env()` 替换为 `Env::get()`

## 快速开始

### 1. 启用 Sentry（可选）

编辑 `.env` 文件，修改以下配置：

```ini
[sentry]
enabled = true
dsn = https://your-sentry-dsn@sentry.io/project-id
environment = development
```

**注意**：如果不配置 DSN，Sentry 将只记录本地日志，不会上报到远程服务器。

### 2. 测试运行

```bash
# 测试 Sentry 配置
php think sentry:test

# 测试异常捕获
php think sentry:test -e

# 自定义测试消息
php think sentry:test -m "我的测试消息" -l warning
```

### 3. 在代码中使用

#### 3.1 捕获异常

```php
use app\common\library\SentryService;

try {
    // 可能出错的代码
    $result = someRiskyOperation();
} catch (\Exception $e) {
    // 记录到 Sentry 和本地日志
    SentryService::captureException($e, [
        'user_id' => $userId,
        'action' => 'create_order',
    ]);
    
    // 继续处理异常...
}
```

#### 3.2 记录错误日志

```php
// 错误级别日志
SentryService::error('订单创建失败', [
    'order_id' => $orderId,
    'error_code' => $errorCode,
]);

// 警告级别
SentryService::warning('库存不足', ['product_id' => 123]);

// 信息级别
SentryService::info('用户登录成功', ['user_id' => 456]);
```

#### 3.3 添加面包屑（事件追踪）

```php
// 追踪用户操作路径
SentryService::addBreadcrumb('用户进入商品详情页', 'navigation', 'info', [
    'product_id' => 123,
    'category' => 'electronics',
]);

SentryService::addBreadcrumb('用户点击购买按钮', 'user', 'info');

// 后续如果发生错误，这些面包屑会一起上报
try {
    createOrder();
} catch (\Exception $e) {
    SentryService::captureException($e); // 会包含之前的面包屑
}
```

### 4. 自动捕获异常（可选）

如果希望自动捕获所有未处理的异常，可以启用异常处理器：

编辑 `application/config.php`：

```php
'exception_handle' => \app\common\library\SentryExceptionHandler::class,
```

启用后，所有异常都会：
1. 记录到 FastAdmin 默认日志
2. 同时上报到 Sentry（如果配置了 DSN）
3. 本地备份到 `runtime/sentry/` 目录

## 本地日志

即使不配置 Sentry DSN，系统也会将日志按日期保存到：

```
runtime/sentry/2026-02-14.log
runtime/sentry/2026-02-15.log
...
```

日志格式示例：
```
[2026-02-14 15:30:45] [ERROR] 订单创建失败 [EventID: null]
[2026-02-14 15:31:20] [WARNING] 库存不足 [EventID: null]
```

## 配置说明

### 完整配置项（application/extra/sentry.php）

```php
return [
    'enabled' => false,              // 是否启用 Sentry
    'dsn' => '',                     // Sentry DSN
    'environment' => 'development',  // 环境标识
    'release' => '1.0.0',            // 应用版本号
    'sample_rate' => 1.0,            // 采样率 (0.0-1.0)
    'send_user_info' => true,        // 是否记录用户信息
    
    'local_log' => [
        'enabled' => true,           // 启用本地日志备份
        'path' => RUNTIME_PATH . 'sentry/',
        'max_files' => 30,           // 保留30天的日志
    ],
    
    // 不上报的异常类型
    'excluded_exceptions' => [
        \think\exception\HttpException::class,
        \think\exception\ValidateException::class,
    ],
    
    // 记录的日志级别
    'log_levels' => [
        'emergency', 'alert', 'critical', 'error', 'warning',
    ],
];
```

## 使用场景示例

### 场景1：调试生产环境问题

```php
// 在关键位置添加调试信息
SentryService::addBreadcrumb('开始处理订单', 'process', 'info', [
    'order_id' => $orderId,
    'status' => 'processing',
]);

// 添加额外上下文
SentryService::setTag('payment_method', 'alipay');
SentryService::setExtra('order_details', $orderData);

// 执行业务逻辑
try {
    processOrder($orderId);
} catch (\Exception $e) {
    // 异常发生时，所有面包屑和额外信息都会一起上报
    SentryService::captureException($e);
}
```

### 场景2：监控关键业务指标

```php
// 监控支付失败
if (!$paymentResult) {
    SentryService::warning('支付失败', [
        'order_id' => $orderId,
        'amount' => $amount,
        'reason' => $failureReason,
    ]);
}

// 监控性能问题
$startTime = microtime(true);
doComplexOperation();
$duration = microtime(true) - $startTime;

if ($duration > 5) {
    SentryService::warning('操作耗时过长', [
        'operation' => 'complex_operation',
        'duration' => $duration,
    ]);
}
```

### 场景3：本地开发调试

即使不配置 Sentry DSN，也可以使用本地日志功能：

```php
// 开发环境下快速调试
SentryService::debug('调试信息', [
    'variable' => $someVariable,
    'state' => $currentState,
]);
```

日志会保存到 `runtime/sentry/` 目录，方便查看。

## 注意事项

1. **不与 FastAdmin 日志冲突**：Sentry 是独立的日志系统，不会影响 FastAdmin 自带的日志功能
2. **敏感信息过滤**：POST 参数中的密码等敏感信息会自动脱敏
3. **性能影响**：本地日志备份几乎无性能影响；如果启用远程上报，建议设置合适的采样率
4. **日志清理**：旧日志会自动清理，默认保留30天

## 故障排查

### 问题：网站报错 "Call to undefined function env()"

**解决**：已修复，确保 `application/extra/sentry.php` 使用 `Env::get()` 而不是 `env()`

### 问题：本地日志未生成

检查目录权限：
```bash
chmod -R 755 runtime/sentry/
```

### 问题：Sentry 上报失败

1. 检查 DSN 配置是否正确
2. 检查网络连接
3. 查看本地日志 `runtime/sentry/` 中的错误信息

## 获取 Sentry DSN

1. 访问 [sentry.io](https://sentry.io)
2. 注册账号并创建项目
3. 在项目设置中找到 DSN，格式类似：
   ```
   https://abcd1234@o123456.ingest.sentry.io/7890123
   ```
4. 复制 DSN 到 `.env` 文件中

如果不需要远程上报功能，可以不配置 DSN，仅使用本地日志功能。
