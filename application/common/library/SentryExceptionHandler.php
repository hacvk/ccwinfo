<?php

namespace app\common\library;

use Exception;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Request;
use think\Response;

/**
 * Sentry 异常处理器
 * 继承自 ThinkPHP 默认异常处理器，在处理异常时额外上报到 Sentry
 * 
 * 使用方式：
 * 1. 在 application/config.php 中设置:
 *    'exception_handle' => \app\common\library\SentryExceptionHandler::class,
 * 
 * 2. 或者在特定模块的 config.php 中单独配置
 */
class SentryExceptionHandler extends Handle
{
    /**
     * 不上报到 Sentry 的异常类型
     * @var array
     */
    protected $ignoreReport = [
        '\\think\\exception\\HttpException',
        '\\think\\exception\\ValidateException',
    ];

    /**
     * 报告异常
     *
     * @param  Exception $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        // 调用父类报告（记录到 FastAdmin 默认日志）
        parent::report($exception);

        // 检查是否应该上报到 Sentry
        if ($this->shouldReportToSentry($exception)) {
            try {
                // 添加请求上下文
                $context = $this->getRequestContext();
                
                // 上报到 Sentry
                SentryService::captureException($exception, $context);
            } catch (\Throwable $e) {
                // Sentry 上报失败时不影响主流程
                // 记录到本地日志
                \think\Log::error('Sentry上报失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 判断是否应该上报异常到 Sentry
     * 
     * @param Exception $exception
     * @return bool
     */
    protected function shouldReportToSentry(Exception $exception): bool
    {
        // 检查 Sentry 是否启用
        if (!SentryService::isEnabled()) {
            return false;
        }

        // 检查是否在忽略列表中
        if ($this->isIgnoreReport($exception)) {
            return false;
        }

        return true;
    }

    /**
     * 获取请求上下文信息
     * 
     * @return array
     */
    protected function getRequestContext(): array
    {
        $context = [];

        try {
            $request = Request::instance();
            
            $context['request'] = [
                'url' => $request->url(true),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
                'referer' => $request->header('referer'),
            ];

            // GET 参数
            if ($request->isGet() && !empty($request->get())) {
                $context['request']['query'] = $request->get();
            }

            // POST 参数（过滤敏感信息）
            if ($request->isPost() && !empty($request->post())) {
                $postData = $request->post();
                $sensitiveKeys = ['password', 'pwd', 'pass', 'token', 'secret', 'key'];
                foreach ($sensitiveKeys as $key) {
                    if (isset($postData[$key])) {
                        $postData[$key] = '******';
                    }
                }
                $context['request']['post'] = $postData;
            }

            // 会话信息
            $session = \think\Session::get();
            if (!empty($session)) {
                $context['session'] = array_keys($session);
            }

        } catch (\Throwable $e) {
            // 获取请求信息失败
            $context['error'] = '获取请求上下文失败: ' . $e->getMessage();
        }

        return $context;
    }

    /**
     * 渲染异常
     *
     * @param  Exception $e
     * @return Response
     */
    public function render(Exception $e)
    {
        $response = parent::render($e);

        // 在调试模式下，添加 Sentry 事件 ID 到响应头
        if (\think\App::$debug && SentryService::isEnabled()) {
            // 获取最近的 Sentry 事件 ID（如果有）
            $hub = SentryService::getHub();
            if ($hub) {
                $lastEventId = $hub->getLastEventId();
                if ($lastEventId) {
                    $response->header(['X-Sentry-Event-ID' => (string)$lastEventId]);
                }
            }
        }

        return $response;
    }
}
