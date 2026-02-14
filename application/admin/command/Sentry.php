<?php

namespace app\admin\command;

use app\common\library\SentryService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * Sentry 测试命令
 * 用于测试 Sentry 集成是否正常工作
 * 
 * 使用方式：
 * php think sentry:test
 * php think sentry:test --message="测试消息"
 * php think sentry:test --exception
 */
class Sentry extends Command
{
    protected function configure()
    {
        $this->setName('sentry:test')
            ->setDescription('测试 Sentry 日志服务集成')
            ->addOption('message', 'm', Option::VALUE_OPTIONAL, '要发送的测试消息', 'Sentry 测试消息')
            ->addOption('exception', 'e', Option::VALUE_NONE, '测试异常捕获')
            ->addOption('level', 'l', Option::VALUE_OPTIONAL, '日志级别', 'error');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('=== Sentry 测试 ===');
        $output->writeln('');

        // 检查是否启用
        if (!SentryService::isEnabled()) {
            $output->error('Sentry 未启用！');
            $output->writeln('');
            $output->writeln('请在 .env 文件中配置以下选项：');
            $output->writeln('  SENTRY_ENABLED=true');
            $output->writeln('  SENTRY_DSN=https://xxxxx@sentry.io/xxx');
            $output->writeln('');
            $output->writeln('或修改 application/extra/sentry.php 配置文件');
            return 1;
        }

        $output->info('Sentry 已启用');

        // 获取配置
        $config = \think\Config::get('sentry');
        $output->writeln("环境: " . ($config['environment'] ?? '未设置'));
        $output->writeln("版本: " . ($config['release'] ?? '未设置'));
        $output->writeln("本地日志: " . ($config['local_log']['enabled'] ? '已启用' : '未启用'));
        $output->writeln('');

        $message = $input->getOption('message');
        $level = $input->getOption('level');
        $testException = $input->getOption('exception');

        if ($testException) {
            // 测试异常捕获
            $output->writeln('正在测试异常捕获...');
            try {
                throw new \RuntimeException('这是一个测试异常 - ' . date('Y-m-d H:i:s'));
            } catch (\Exception $e) {
                $eventId = SentryService::captureException($e, [
                    'test' => true,
                    'timestamp' => time(),
                ]);
                
                if ($eventId) {
                    $output->info("异常已上报! Event ID: {$eventId}");
                } else {
                    $output->error('异常上报失败');
                }
            }
        } else {
            // 测试消息发送
            $output->writeln("正在发送测试消息 (级别: {$level})...");
            
            $eventId = SentryService::captureMessage($message, $level, [
                'test' => true,
                'timestamp' => time(),
                'source' => 'command_line',
            ]);

            if ($eventId) {
                $output->info("消息已发送! Event ID: {$eventId}");
            } else {
                $output->error('消息发送失败');
            }
        }

        // 检查本地日志
        $logPath = RUNTIME_PATH . 'sentry' . DS;
        if (is_dir($logPath)) {
            $logFile = $logPath . date('Y-m-d') . '.log';
            if (file_exists($logFile)) {
                $output->writeln('');
                $output->writeln("本地日志文件: {$logFile}");
                $output->writeln('最近的日志内容：');
                $output->writeln('---');
                $lines = file($logFile);
                $lastLines = array_slice($lines, -5);
                foreach ($lastLines as $line) {
                    $output->writeln('  ' . trim($line));
                }
                $output->writeln('---');
            }
        }

        $output->writeln('');
        $output->writeln('测试完成！');

        return 0;
    }
}
