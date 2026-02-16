<?php

namespace app\common\library;

use Psr\Log\LogLevel;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Severity;
use Sentry\State\Scope;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Breadcrumb;
use think\Config;
use think\Request;

/**
 * Sentry 日志服务类
 * 独立于 FastAdmin 自带日志系统，用于调试和错误追踪
 * 
 * 使用方式:
 *   SentryService::captureException($exception);  // 捕获异常
 *   SentryService::error('错误信息');             // 记录错误
 *   SentryService::info('信息');                  // 记录信息
 *   SentryService::captureMessage('消息', 'error'); // 发送消息
 */
class SentryService
{
    /**
     * @var HubInterface|null
     */
    protected static $hub = null;

    /**
     * @var array 配置
     */
    protected static $config = [];

    /**
     * @var bool 是否已初始化
     */
    protected static $initialized = false;

    /**
     * 日志级别映射
     */
    protected static $levelMap = null;

    /**
     * 获取日志级别映射
     */
    protected static function getLevelMap(): array
    {
        if (self::$levelMap === null) {
            self::$levelMap = [
                LogLevel::DEBUG => Severity::debug(),
                LogLevel::INFO => Severity::info(),
                LogLevel::NOTICE => Severity::info(),
                LogLevel::WARNING => Severity::warning(),
                LogLevel::ERROR => Severity::error(),
                LogLevel::CRITICAL => Severity::fatal(),
                LogLevel::ALERT => Severity::fatal(),
                LogLevel::EMERGENCY => Severity::fatal(),
            ];
        }
        return self::$levelMap;
    }

    /**
     * 初始化 Sentry
     */
    public static function init(): bool
    {
        if (self::$initialized) {
            return true;
        }

        self::$config = Config::get('sentry');

        if (empty(self::$config['enabled']) || empty(self::$config['dsn'])) {
            self::$initialized = true;
            return false;
        }

        try {
            $clientBuilder = ClientBuilder::create([
                'dsn' => self::$config['dsn'],
                'environment' => self::$config['environment'] ?? 'development',
                'release' => self::$config['release'] ?? '1.0.0',
                'sample_rate' => (float)(self::$config['sample_rate'] ?? 1.0),
                'traces_sample_rate' => (float)(self::$config['traces_sample_rate'] ?? 0.1),
                'attach_stacktrace' => true,
                'send_default_pii' => true,
                'max_breadcrumbs' => 50,
                'before_send' => function (Event $event, ?EventHint $hint): ?Event {
                    return self::beforeSend($event, $hint);
                },
            ]);

            $client = $clientBuilder->getClient();
            self::$hub = new Hub($client);

            // 设置默认标签
            if (!empty(self::$config['tags']) && is_array(self::$config['tags'])) {
                self::$hub->configureScope(function (Scope $scope) {
                    foreach (self::$config['tags'] as $key => $value) {
                        $scope->setTag($key, $value);
                    }
                });
            }

            // 设置用户信息
            if (!empty(self::$config['send_user_info'])) {
                self::setUserInfo();
            }

            self::$initialized = true;
            return true;
        } catch (\Exception $e) {
            // 初始化失败，记录本地日志
            self::$initialized = true;
            self::writeLocalLog('error', 'Sentry初始化失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 发送前处理
     */
    protected static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        // 检查排除的异常类型
        if ($hint && $hint->exception && !empty(self::$config['excluded_exceptions'])) {
            $exceptionClass = get_class($hint->exception);
            foreach (self::$config['excluded_exceptions'] as $excludedClass) {
                if ($exceptionClass === $excludedClass || is_a($hint->exception, $excludedClass)) {
                    return null;
                }
            }
        }

        return $event;
    }

    /**
     * 设置用户信息
     */
    protected static function setUserInfo(): void
    {
        if (!self::$hub) {
            return;
        }

        self::$hub->configureScope(function (Scope $scope) {
            $user = [];

            // 尝试获取后台管理员信息
            try {
                $admin = \think\Session::get('admin');
                if ($admin) {
                    $user = [
                        'id' => $admin['id'] ?? 0,
                        'username' => $admin['username'] ?? '',
                        'email' => $admin['email'] ?? '',
                        'type' => 'admin',
                    ];
                }
            } catch (\Exception $e) {
                // 忽略
            }

            // 尝试获取前台用户信息
            try {
                $frontendUser = \think\Session::get('user');
                if ($frontendUser) {
                    $user = [
                        'id' => $frontendUser['id'] ?? 0,
                        'username' => $frontendUser['username'] ?? '',
                        'email' => $frontendUser['email'] ?? '',
                        'type' => 'frontend',
                    ];
                }
            } catch (\Exception $e) {
                // 忽略
            }

            // 添加请求信息
            try {
                $request = Request::instance();
                $user['ip_address'] = $request->ip();
            } catch (\Exception $e) {
                // 忽略
            }

            if (!empty($user)) {
                $scope->setUser($user);
            }
        });
    }

    /**
     * 捕获异常
     * 
     * @param \Throwable $exception 异常对象
     * @param array $context 上下文数据
     * @return string|null 事件ID
     */
    public static function captureException(\Throwable $exception, array $context = []): ?string
    {
        $config = Config::get('sentry');
        if (empty($config['enabled']) || empty($config['dsn'])) {
            self::$config = $config;
            self::$initialized = true;
            self::writeLocalLog('error', self::formatException($exception));
            return null;
        }

        if (!self::init()) {
            self::writeLocalLog('error', self::formatException($exception));
            return null;
        }

        $eventId = null;
        
        self::$hub->configureScope(function (Scope $scope) use ($context) {
            // 添加上下文
            if (!empty($context)) {
                foreach ($context as $key => $value) {
                    if (is_array($value)) {
                        $scope->setContext($key, $value);
                    } else {
                        $scope->setExtra($key, $value);
                    }
                }
            }
        });

        $eventId = self::$hub->captureException($exception);

        // 本地日志备份
        self::writeLocalLog('error', self::formatException($exception) . ' [EventID: ' . $eventId . ']');

        return $eventId;
    }

    /**
     * 捕获消息
     * 
     * @param string $message 消息内容
     * @param string $level 日志级别
     * @param array $context 上下文数据
     * @return string|null 事件ID
     */
    public static function captureMessage(string $message, string $level = 'info', array $context = []): ?string
    {
        $config = Config::get('sentry');
        if (empty($config['enabled']) || empty($config['dsn'])) {
            self::$config = $config;
            self::$initialized = true;
            self::writeLocalLog($level, $message);
            return null;
        }

        if (!self::init()) {
            self::writeLocalLog($level, $message);
            return null;
        }

        // 检查日志级别是否在配置的记录范围内
        if (!in_array($level, self::$config['log_levels'] ?? [])) {
            self::writeLocalLog($level, $message);
            return null;
        }

        $levelMap = self::getLevelMap();
        $severity = $levelMap[$level] ?? Severity::info();

        self::$hub->configureScope(function (Scope $scope) use ($context) {
            if (!empty($context)) {
                foreach ($context as $key => $value) {
                    if (is_array($value)) {
                        $scope->setContext($key, $value);
                    } else {
                        $scope->setExtra($key, $value);
                    }
                }
            }
        });

        $eventId = self::$hub->captureMessage($message, $severity);

        // 本地日志备份
        self::writeLocalLog($level, $message . ' [EventID: ' . $eventId . ']');

        return $eventId;
    }

    /**
     * 记录日志 (PSR-3 风格)
     * 
     * @param mixed $level 日志级别
     * @param string $message 消息
     * @param array $context 上下文
     * @return string|null
     */
    public static function log($level, string $message, array $context = []): ?string
    {
        return self::captureMessage($message, (string)$level, $context);
    }

    /**
     * 快捷方法 - 紧急情况
     */
    public static function emergency(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::EMERGENCY, $context);
    }

    /**
     * 快捷方法 - 警报
     */
    public static function alert(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::ALERT, $context);
    }

    /**
     * 快捷方法 - 严重错误
     */
    public static function critical(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::CRITICAL, $context);
    }

    /**
     * 快捷方法 - 错误
     */
    public static function error(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::ERROR, $context);
    }

    /**
     * 快捷方法 - 警告
     */
    public static function warning(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::WARNING, $context);
    }

    /**
     * 快捷方法 - 通知
     */
    public static function notice(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::NOTICE, $context);
    }

    /**
     * 快捷方法 - 信息
     */
    public static function info(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::INFO, $context);
    }

    /**
     * 快捷方法 - 调试
     */
    public static function debug(string $message, array $context = []): ?string
    {
        return self::captureMessage($message, LogLevel::DEBUG, $context);
    }

    /**
     * 添加面包屑 (用于追踪事件链)
     * 
     * @param string $message 消息
     * @param string $category 分类
     * @param string $level 级别
     * @param array $metadata 元数据
     */
    public static function addBreadcrumb(string $message, string $category = 'custom', string $level = 'info', array $metadata = []): void
    {
        $config = Config::get('sentry');
        if (empty($config['enabled']) || empty($config['dsn'])) {
            self::$config = $config;
            self::$initialized = true;
            return;
        }

        if (!self::init()) {
            return;
        }

        $levelMap = self::getLevelMap();
        $severity = $levelMap[$level] ?? Severity::info();
        
        // 将 Severity 转换为 Breadcrumb 级别字符串
        $breadcrumbLevel = match ($severity->__toString()) {
            'debug' => Breadcrumb::LEVEL_DEBUG,
            'info' => Breadcrumb::LEVEL_INFO,
            'warning' => Breadcrumb::LEVEL_WARNING,
            'error' => Breadcrumb::LEVEL_ERROR,
            'fatal' => Breadcrumb::LEVEL_FATAL,
            default => Breadcrumb::LEVEL_INFO,
        };

        $breadcrumb = new Breadcrumb(
            $breadcrumbLevel,
            Breadcrumb::TYPE_DEFAULT,
            $category,
            $message,
            $metadata
        );

        self::$hub->addBreadcrumb($breadcrumb);
    }

    /**
     * 设置标签
     * 
     * @param string $key 键名
     * @param string $value 值
     */
    public static function setTag(string $key, string $value): void
    {
        $config = Config::get('sentry');
        if (empty($config['enabled']) || empty($config['dsn'])) {
            self::$config = $config;
            self::$initialized = true;
            return;
        }

        if (!self::init()) {
            return;
        }

        self::$hub->configureScope(function (Scope $scope) use ($key, $value) {
            $scope->setTag($key, $value);
        });
    }

    /**
     * 设置额外数据
     * 
     * @param string $key 键名
     * @param mixed $value 值
     */
    public static function setExtra(string $key, $value): void
    {
        $config = Config::get('sentry');
        if (empty($config['enabled']) || empty($config['dsn'])) {
            self::$config = $config;
            self::$initialized = true;
            return;
        }

        if (!self::init()) {
            return;
        }

        self::$hub->configureScope(function (Scope $scope) use ($key, $value) {
            $scope->setExtra($key, $value);
        });
    }

    /**
     * 格式化异常信息
     */
    protected static function formatException(\Throwable $exception): string
    {
        return sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }

    /**
     * 写入本地日志 (按日期存放)
     * 
     * @param string $level 日志级别
     * @param string $message 日志消息
     */
    protected static function writeLocalLog(string $level, string $message): void
    {
        if (empty(self::$config)) {
            self::$config = Config::get('sentry');
        }
        
        $localLogConfig = self::$config['local_log'] ?? [];
        
        if (empty($localLogConfig['enabled'])) {
            return;
        }

        $logPath = $localLogConfig['path'] ?? RUNTIME_PATH . 'sentry' . DS;
        
        // 确保目录存在
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        // 按日期存放
        $filename = $logPath . date('Y-m-d') . '.log';
        
        // 格式化日志内容
        $content = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        // 写入文件
        file_put_contents($filename, $content, FILE_APPEND | LOCK_EX);

        // 清理过期日志
        self::cleanupOldLogs($logPath, $localLogConfig['max_files'] ?? 30);
    }

    /**
     * 清理过期日志文件
     * 
     * @param string $logPath 日志目录
     * @param int $maxDays 最大保留天数
     */
    protected static function cleanupOldLogs(string $logPath, int $maxDays): void
    {
        if (!is_dir($logPath)) {
            return;
        }

        $files = glob($logPath . '*.log');
        if (!$files) {
            return;
        }

        $expireTime = time() - ($maxDays * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $expireTime) {
                @unlink($file);
            }
        }
    }

    /**
     * 获取 Hub 实例
     * 
     * @return HubInterface|null
     */
    public static function getHub(): ?HubInterface
    {
        self::init();
        return self::$hub;
    }

    /**
     * 检查是否已启用
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        $config = Config::get('sentry');
        return !empty($config['enabled']) && !empty($config['dsn']);
    }
}
