<?php

use think\Env;

/**
 * Sentry 日志服务配置
 * 用于调试和错误追踪，独立于FastAdmin自带日志系统
 */
return [
    // 是否启用 Sentry
    'enabled' => Env::get('sentry.enabled', false),
    
    // Sentry DSN (从 Sentry 项目设置中获取)
    'dsn' => Env::get('sentry.dsn', ''),
    
    // 环境标识 (development, staging, production)
    'environment' => Env::get('sentry.environment', 'development'),
    
    // 应用版本号
    'release' => Env::get('sentry.release', '1.0.0'),
    
    // 采样率 (0.0 - 1.0)，1.0 表示100%上报
    'sample_rate' => Env::get('sentry.sample_rate', 1.0),
    
    // 追踪采样率
    'traces_sample_rate' => Env::get('sentry.traces_sample_rate', 0.1),
    
    // 是否记录用户信息
    'send_user_info' => Env::get('sentry.send_user_info', true),
    
    // 本地日志备份配置 (按日期存放)
    'local_log' => [
        // 是否启用本地日志备份
        'enabled' => Env::get('sentry.local_log.enabled', true),
        // 本地日志存放路径
        'path' => RUNTIME_PATH . 'sentry' . DS,
        // 日志保留天数
        'max_files' => Env::get('sentry.local_log.max_files', 30),
    ],
    
    // 排除的异常类型 (这些异常不会上报到 Sentry)
    'excluded_exceptions' => [
        \think\exception\HttpException::class,
        \think\exception\ValidateException::class,
    ],
    
    // 需要记录的日志级别
    'log_levels' => [
        'emergency',
        'alert', 
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ],
    
    // 是否在控制台显示 Sentry 事件ID
    'display_event_id' => Env::get('sentry.display_event_id', true),
    
    // 附加标签
    'tags' => [
        'app_name' => '东营查查网',
        'php_version' => PHP_VERSION,
    ],
];
