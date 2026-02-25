<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\AliyunSms;
use app\common\library\Email;
use app\common\library\SentryService;
use app\common\model\Sms;
use app\common\model\SmsLog;
use think\Config;
use think\Db;
use think\Validate;

/**
 * 通知中心管理
 */
class Notice extends Backend
{
    protected $model = null;
    protected $noNeedRight = [''];
    protected $smsFields = null;

    /**
     * OTP 默认过期秒数（旧表无 expire_at 时用于兼容）
     *
     * @var int
     */
    protected $otpExpireSeconds = 600;

    /**
     * OTP 单条最大错误次数（与前台一致）
     *
     * @var int
     */
    protected $otpMaxCheckTimes = 10;

    public function _initialize()
    {
        parent::_initialize();
    }

    /**
     * 通知中心主页
     */
    public function index()
    {
        $tab = trim((string)$this->request->get('tab', 'logs'));
        if (!in_array($tab, ['logs', 'otp', 'aliyun', 'mail'], true)) {
            $tab = 'logs';
        }
        $this->assignNoticeView($tab);

        return $this->view->fetch();
    }

    /**
     * 短信发送日志
     */
    public function logs()
    {
        $this->model = new SmsLog();

        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            $isSuper = $this->auth->isSuperAdmin();
            foreach ($list as &$row) {
                $row['scene'] = (string)($row['scene'] ?? '') ?: '-';
                $row['channel'] = (string)($row['channel'] ?? '') ?: 'qcloud';
                $row['provider_biz_id'] = (string)($row['provider_biz_id'] ?? '') ?: '-';
                $row['payload_text'] = mb_substr((string)($row['request_payload'] ?? ''), 0, 160) ?: '-';

                if (!$isSuper) {
                    $row['phone'] = AliyunSms::maskPhone((string)$row['phone']);
                }
            }
            unset($row);

            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        $this->assignNoticeView('logs');
        return $this->view->fetch('index');
    }

    /**
     * 验证码记录
     */
    public function otp()
    {
        $this->model = new Sms();

        if ($this->request->isAjax()) {
            $status = $this->normalizeOtpStatusFilter();
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $now = time();
            $hasUsedAt = $this->hasSmsField('used_at');
            $hasExpireAt = $this->hasSmsField('expire_at');

            $query = $this->model->where($where);

            if ($status === 'used') {
                if ($hasUsedAt) {
                    $query->where('used_at', '>', 0);
                } else {
                    $query->where('times', '>', $this->otpMaxCheckTimes);
                }
            } elseif ($status === 'unused') {
                if ($hasUsedAt && $hasExpireAt) {
                    $query->where('used_at', 0)->where('expire_at', '>', $now);
                } elseif ($hasUsedAt) {
                    $query->where('used_at', 0)->where('createtime', '>', $now - $this->otpExpireSeconds);
                } elseif ($hasExpireAt) {
                    $query->where('times', '<=', $this->otpMaxCheckTimes)->where('expire_at', '>', $now);
                } else {
                    $query->where('times', '<=', $this->otpMaxCheckTimes)->where('createtime', '>', $now - $this->otpExpireSeconds);
                }
            } elseif ($status === 'expired') {
                if ($hasUsedAt && $hasExpireAt) {
                    $query->where('used_at', 0)->where('expire_at', '<=', $now);
                } elseif ($hasUsedAt) {
                    $query->where('used_at', 0)->where('createtime', '<=', $now - $this->otpExpireSeconds);
                } elseif ($hasExpireAt) {
                    $query->where('times', '<=', $this->otpMaxCheckTimes)->where('expire_at', '<=', $now);
                } else {
                    $query->where('times', '<=', $this->otpMaxCheckTimes)->where('createtime', '<=', $now - $this->otpExpireSeconds);
                }
            }

            $list = $query->order($sort, $order)->paginate($limit);

            $isSuper = $this->auth->isSuperAdmin();
            foreach ($list as &$row) {
                $times = (int)($row['times'] ?? 0);
                $usedAt = $hasUsedAt ? (int)($row['used_at'] ?? 0) : 0;
                $expireAt = $hasExpireAt ? (int)($row['expire_at'] ?? 0) : ((int)($row['createtime'] ?? 0) + $this->otpExpireSeconds);
                $isUsed = $hasUsedAt ? ($usedAt > 0) : ($times > $this->otpMaxCheckTimes);

                if ($expireAt <= 0) {
                    $expireAt = null;
                }

                if (!$isSuper) {
                    $row['mobile'] = AliyunSms::maskPhone((string)$row['mobile']);
                    $row['code'] = AliyunSms::maskCode((string)$row['code']);
                }

                $row['type'] = (string)($row['type'] ?? '') ?: '-';
                $row['info_id'] = (int)($row['info_id'] ?? 0);
                $row['used_at'] = ($hasUsedAt && $usedAt > 0) ? $usedAt : null;
                $row['expire_at'] = $expireAt;

                if ($isUsed) {
                    $row['otp_status'] = '已使用';
                } elseif (!is_null($expireAt) && $expireAt <= $now) {
                    $row['otp_status'] = '已过期';
                } else {
                    $row['otp_status'] = '待使用';
                }
            }
            unset($row);

            $result = ['total' => $list->total(), 'rows' => $list->items()];
            return json($result);
        }
        $this->assignNoticeView('otp');
        return $this->view->fetch('index');
    }

    /**
     * 阿里云短信配置
     */
    public function aliyun()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }

            $configData = [
                'aliyun_sms_access_key_id' => trim((string)($params['aliyun_sms_access_key_id'] ?? '')),
                'aliyun_sms_access_key_secret' => trim((string)($params['aliyun_sms_access_key_secret'] ?? '')),
                'aliyun_sms_sign_name' => trim((string)($params['aliyun_sms_sign_name'] ?? '')),
                'aliyun_sms_region_id' => trim((string)($params['aliyun_sms_region_id'] ?? 'cn-hangzhou')) ?: 'cn-hangzhou',
                'aliyun_sms_endpoint' => trim((string)($params['aliyun_sms_endpoint'] ?? 'dysmsapi.aliyuncs.com')) ?: 'dysmsapi.aliyuncs.com',
            ];

            $saveResult = $this->saveSiteConfig($configData);
            if (!$saveResult['ok']) {
                $this->error($saveResult['msg']);
            }

            $this->success('保存成功');
        }

        $this->assignNoticeView('aliyun');
        return $this->view->fetch();
    }

    /**
     * 邮件配置
     */
    public function mail()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }

            $configData = [
                'mail_type' => (string)((int)($params['mail_type'] ?? 1)),
                'mail_smtp_host' => trim((string)($params['mail_smtp_host'] ?? '')),
                'mail_smtp_port' => trim((string)($params['mail_smtp_port'] ?? '465')),
                'mail_smtp_user' => trim((string)($params['mail_smtp_user'] ?? '')),
                'mail_smtp_pass' => trim((string)($params['mail_smtp_pass'] ?? '')),
                'mail_verify_type' => (string)((int)($params['mail_verify_type'] ?? 2)),
                'mail_from' => trim((string)($params['mail_from'] ?? '')),
            ];

            if (!in_array($configData['mail_type'], ['0', '1', '2'], true)) {
                $configData['mail_type'] = '1';
            }
            if (!in_array($configData['mail_verify_type'], ['0', '1', '2'], true)) {
                $configData['mail_verify_type'] = '2';
            }
            if ($configData['mail_smtp_port'] !== '' && !preg_match('/^\d{1,5}$/', $configData['mail_smtp_port'])) {
                $this->error('SMTP 端口格式不正确');
            }
            if ($configData['mail_from'] !== '' && !Validate::is($configData['mail_from'], 'email')) {
                $this->error('发件人邮箱格式不正确');
            }

            $saveResult = $this->saveSiteConfig($configData);
            if (!$saveResult['ok']) {
                $this->error($saveResult['msg']);
            }

            $this->success('保存成功');
        }

        $this->assignNoticeView('mail');
        return $this->view->fetch('index');
    }

    /**
     * 测试阿里云短信发送
     */
    public function testAliyunSms()
    {
        $phone = trim((string)$this->request->post('phone', ''));

        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $this->error('请输入正确的手机号');
        }

        $config = [
            'aliyun_sms_access_key_id' => (string)$this->request->post('aliyun_sms_access_key_id', Config::get('site.aliyun_sms_access_key_id') ?: ''),
            'aliyun_sms_access_key_secret' => (string)$this->request->post('aliyun_sms_access_key_secret', Config::get('site.aliyun_sms_access_key_secret') ?: ''),
            'aliyun_sms_sign_name' => (string)$this->request->post('aliyun_sms_sign_name', Config::get('site.aliyun_sms_sign_name') ?: ''),
            'aliyun_sms_region_id' => (string)$this->request->post('aliyun_sms_region_id', Config::get('site.aliyun_sms_region_id') ?: 'cn-hangzhou'),
            'aliyun_sms_endpoint' => (string)$this->request->post('aliyun_sms_endpoint', Config::get('site.aliyun_sms_endpoint') ?: 'dysmsapi.aliyuncs.com'),
        ];

        if (empty($config['aliyun_sms_access_key_id']) || empty($config['aliyun_sms_access_key_secret'])) {
            $this->error('请先配置 AccessKey ID 和 Secret');
        }
        if (empty($config['aliyun_sms_sign_name'])) {
            $this->error('请先配置短信签名');
        }

        $saveResult = $this->saveSiteConfig($config);
        if (!$saveResult['ok']) {
            $this->error($saveResult['msg']);
        }

        $code = mt_rand(100000, 999999);
        $templateCode = 'SMS_123456789';
        $result = AliyunSms::send($phone, $templateCode, ['code' => (string)$code], 'test');

        if (!empty($result['success'])) {
            $this->success('短信发送成功，验证码：' . $code);
        }
        $error = (string)($result['error'] ?? '未知错误');
        $this->captureNoticeSendFailure('通知中心短信发送失败', $this->buildNoticeSendFailureContext('sms', 'testAliyunSms', [
            'scene' => 'test',
            'phone_masked' => AliyunSms::maskPhone($phone),
            'template_code' => $templateCode,
            'error' => $error,
        ]));
        $this->error('短信发送失败：' . $error);
    }

    /**
     * 测试邮件发送
     */
    public function testMail()
    {
        $receiver = trim((string)$this->request->post('receiver', ''));
        if (!Validate::is($receiver, 'email')) {
            $this->error('请输入正确的收件邮箱');
        }

        $posted = $this->request->post('row/a', []);
        $mailConfig = $this->getMailConfig();

        if (!empty($posted)) {
            $mailConfig = array_merge($mailConfig, [
                'mail_type' => (string)((int)($posted['mail_type'] ?? $mailConfig['mail_type'])),
                'mail_smtp_host' => trim((string)($posted['mail_smtp_host'] ?? $mailConfig['mail_smtp_host'])),
                'mail_smtp_port' => trim((string)($posted['mail_smtp_port'] ?? $mailConfig['mail_smtp_port'])),
                'mail_smtp_user' => trim((string)($posted['mail_smtp_user'] ?? $mailConfig['mail_smtp_user'])),
                'mail_smtp_pass' => trim((string)($posted['mail_smtp_pass'] ?? $mailConfig['mail_smtp_pass'])),
                'mail_verify_type' => (string)((int)($posted['mail_verify_type'] ?? $mailConfig['mail_verify_type'])),
                'mail_from' => trim((string)($posted['mail_from'] ?? $mailConfig['mail_from'])),
            ]);
        }

        if (!in_array((string)$mailConfig['mail_type'], ['1', '2'], true)) {
            $this->error('请先开启邮件发送方式');
        }
        if ($mailConfig['mail_smtp_host'] === '' || $mailConfig['mail_smtp_port'] === '' || $mailConfig['mail_from'] === '' || $mailConfig['mail_smtp_pass'] === '') {
            $this->error('请先完整配置 SMTP 主机、端口、发件邮箱、SMTP 密码');
        }
        if (!Validate::is($mailConfig['mail_from'], 'email')) {
            $this->error('发件人邮箱格式不正确');
        }

        Config::set('site', array_merge((array)Config::get('site'), $mailConfig));

        $subject = sprintf('通知中心测试邮件 - %s', (string)(config('site.name') ?: '站点'));
        $content = '<div style="padding:24px;font-size:14px;line-height:1.8;">这是一封来自通知中心的测试邮件，发送时间：' . date('Y-m-d H:i:s') . '</div>';
        $email = new Email();
        $result = $email->to($receiver)->subject($subject)->message($content)->send();

        if ($result) {
            $this->success('测试邮件发送成功');
        }
        $error = (string)($email->getError() ?: '未知错误');
        $this->captureNoticeSendFailure('通知中心邮件发送失败', $this->buildNoticeSendFailureContext('mail', 'testMail', [
            'receiver_masked' => $this->maskEmail($receiver),
            'mail_from_masked' => $this->maskEmail((string)$mailConfig['mail_from']),
            'error' => $error,
        ]));
        $this->error('测试邮件发送失败：' . $error);
    }

    /**
     * 清理历史数据
     */
    public function cleanup()
    {
        if (!$this->auth->isSuperAdmin()) {
            $this->error('仅超级管理员允许执行清理');
        }

        $otpDays = (int)$this->request->post('otp_days', 30);
        $smsDays = (int)$this->request->post('sms_days', 180);

        $otpTime = time() - $otpDays * 86400;
        $smsTime = time() - $smsDays * 86400;

        $otpCount = Sms::where('createtime', '<', $otpTime)->delete();
        $smsCount = SmsLog::where('createtime', '<', $smsTime)->delete();

        $this->success(sprintf('清理完成：验证码 %d 条，短信日志 %d 条', $otpCount, $smsCount));
    }

    /**
     * 兼容前端 otp_status 虚拟筛选字段
     *
     * @return string
     */
    protected function normalizeOtpStatusFilter()
    {
        $status = trim((string)$this->request->get('status', ''));
        $filter = (string)$this->request->get('filter', '');
        $op = (string)$this->request->get('op', '');

        $filterArr = (array)json_decode($filter, true);
        $opArr = (array)json_decode($op, true);

        if ($status === '' && isset($filterArr['otp_status'])) {
            $status = trim((string)$filterArr['otp_status']);
        }

        if (isset($filterArr['otp_status'])) {
            unset($filterArr['otp_status']);
            $newFilter = empty($filterArr) ? '{}' : json_encode($filterArr, JSON_UNESCAPED_UNICODE);
            $this->request->get(['filter' => $newFilter ?: '{}']);
        }
        if (isset($opArr['otp_status'])) {
            unset($opArr['otp_status']);
            $newOp = empty($opArr) ? '{}' : json_encode($opArr, JSON_UNESCAPED_UNICODE);
            $this->request->get(['op' => $newOp ?: '{}']);
        }

        if (!in_array($status, ['used', 'unused', 'expired'], true)) {
            $status = '';
        }
        return $status;
    }

    protected function getSmsFields()
    {
        if (is_array($this->smsFields)) {
            return $this->smsFields;
        }

        $this->smsFields = [];
        try {
            $prefix = (string)Config::get('database.prefix');
            $table = $prefix . 'sms';
            $columns = Db::query("SHOW COLUMNS FROM `{$table}`");
            foreach ((array)$columns as $column) {
                $field = trim((string)($column['Field'] ?? ''));
                if ($field !== '') {
                    $this->smsFields[] = $field;
                }
            }
        } catch (\Throwable $e) {
            $this->smsFields = [];
        }

        return $this->smsFields;
    }

    protected function hasSmsField($field)
    {
        return in_array((string)$field, $this->getSmsFields(), true);
    }

    protected function getAliyunConfig()
    {
        return [
            'aliyun_sms_access_key_id' => $this->readConfigValue('aliyun_sms_access_key_id', ''),
            'aliyun_sms_access_key_secret' => $this->readConfigValue('aliyun_sms_access_key_secret', ''),
            'aliyun_sms_sign_name' => $this->readConfigValue('aliyun_sms_sign_name', ''),
            'aliyun_sms_region_id' => $this->readConfigValue('aliyun_sms_region_id', 'cn-hangzhou'),
            'aliyun_sms_endpoint' => $this->readConfigValue('aliyun_sms_endpoint', 'dysmsapi.aliyuncs.com'),
        ];
    }

    protected function getMailConfig()
    {
        return [
            'mail_type' => $this->readConfigValue('mail_type', '1'),
            'mail_smtp_host' => $this->readConfigValue('mail_smtp_host', 'smtp.qq.com'),
            'mail_smtp_port' => $this->readConfigValue('mail_smtp_port', '465'),
            'mail_smtp_user' => $this->readConfigValue('mail_smtp_user', ''),
            'mail_smtp_pass' => $this->readConfigValue('mail_smtp_pass', ''),
            'mail_verify_type' => $this->readConfigValue('mail_verify_type', '2'),
            'mail_from' => $this->readConfigValue('mail_from', ''),
        ];
    }

    /**
     * 读取配置值：优先 DB，兼容 site 配置，且保留字符串 "0"
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    protected function readConfigValue($name, $default = '')
    {
        $name = (string)$name;
        try {
            $row = Db::name('config')->where('name', $name)->field('value')->find();
            if (is_array($row) && array_key_exists('value', $row)) {
                $value = $row['value'];
                if ($value !== null && $value !== '') {
                    return (string)$value;
                }
                if ((string)$value === '0') {
                    return '0';
                }
            }
        } catch (\Throwable $e) {
        }

        $value = Config::get('site.' . $name);
        if ($value !== null && $value !== '') {
            return (string)$value;
        }
        if ((string)$value === '0') {
            return '0';
        }
        return (string)$default;
    }

    protected function getNoticeConfig()
    {
        return array_merge($this->getAliyunConfig(), $this->getMailConfig());
    }

    protected function assignNoticeView($tab = 'logs')
    {
        $tab = trim((string)$tab);
        if (!in_array($tab, ['logs', 'otp', 'aliyun', 'mail'], true)) {
            $tab = 'logs';
        }
        $this->assign('tab', $tab);
        $this->assign('isSuper', $this->auth->isSuperAdmin());
        $this->assign('otpDays', 30);
        $this->assign('smsDays', 180);
        $this->assign('row', $this->getNoticeConfig());
    }

    /**
     * 保存配置并刷新 application/extra/site.php
     *
     * @param array $configData
     * @return array
     */
    protected function saveSiteConfig(array $configData)
    {
        if (empty($configData)) {
            return ['ok' => false, 'msg' => '配置不能为空'];
        }

        Db::startTrans();
        try {
            foreach ($configData as $name => $value) {
                $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
                $exists = Db::name('config')->where('name', $name)->find();
                if ($exists) {
                    Db::name('config')->where('name', $name)->update(['value' => $value]);
                } else {
                    $insert = $this->buildConfigInsertRow($name, $value);
                    Db::name('config')->insert($insert);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'msg' => '保存配置失败：' . $e->getMessage()];
        }

        try {
            $site = $this->buildSiteConfigFromDb();
            Config::set('site', $site);
            file_put_contents(
                CONF_PATH . 'extra' . DS . 'site.php',
                '<?php' . "\n\nreturn " . var_export($site, true) . ";\n"
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => '配置已保存到数据库，但刷新 site.php 失败：' . $e->getMessage()];
        }

        return ['ok' => true, 'msg' => 'ok'];
    }

    protected function buildSiteConfigFromDb()
    {
        $site = [];
        $configList = Db::name('config')->select();
        foreach ((array)$configList as $row) {
            $name = (string)($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $type = (string)($row['type'] ?? 'string');
            $value = $row['value'] ?? '';
            if (in_array($type, ['selects', 'checkbox', 'images', 'files'], true)) {
                $value = $value === '' ? [] : explode(',', (string)$value);
            } elseif ($type === 'array') {
                $decoded = json_decode((string)$value, true);
                $value = is_array($decoded) ? $decoded : [];
            }
            $site[$name] = $value;
        }
        return $site;
    }

    protected function buildConfigInsertRow($name, $value)
    {
        $name = (string)$name;
        $value = (string)$value;

        $group = 'basic';
        $title = $name;
        $tip = '';
        $type = 'string';
        $content = '';

        if (strpos($name, 'aliyun_sms_') === 0) {
            $group = 'sms';
            $title = $name;
            $type = ($name === 'aliyun_sms_access_key_secret') ? 'password' : 'string';
        } elseif (strpos($name, 'mail_') === 0) {
            $group = 'email';
            $title = $name;
            if ($name === 'mail_type') {
                $type = 'select';
                $content = '["请选择","SMTP"]';
            } elseif ($name === 'mail_verify_type') {
                $type = 'select';
                $content = '["无","TLS","SSL"]';
            } elseif ($name === 'mail_smtp_pass') {
                $type = 'password';
            } else {
                $type = 'string';
            }
        }

        return [
            'name' => $name,
            'group' => $group,
            'title' => $title,
            'tip' => $tip,
            'type' => $type,
            'visible' => '',
            'value' => $value,
            'content' => $content,
            'rule' => '',
            'extend' => '',
            'setting' => '',
        ];
    }

    /**
     * 构建通知中心发送失败上报上下文
     *
     * @param string $channel
     * @param string $action
     * @param array $extra
     * @return array
     */
    protected function buildNoticeSendFailureContext($channel, $action, array $extra = [])
    {
        $context = [
            'module' => 'admin.notice',
            'channel' => (string)$channel,
            'action' => (string)$action,
            'admin_id' => (int)$this->auth->id,
            'admin_username' => (string)$this->auth->username,
            'request_ip' => (string)$this->request->ip(),
            'request_url' => (string)$this->request->url(),
        ];

        return array_merge($context, $extra);
    }

    /**
     * 上报通知中心发送失败日志（上报失败不影响主流程）
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function captureNoticeSendFailure($message, array $context = [])
    {
        try {
            SentryService::error((string)$message, $context);
        } catch (\Throwable $e) {
        }
    }

    /**
     * 邮箱脱敏
     *
     * @param string $email
     * @return string
     */
    protected function maskEmail($email)
    {
        $email = trim((string)$email);
        if ($email === '' || strpos($email, '@') === false) {
            return $email;
        }
        list($name, $domain) = explode('@', $email, 2);
        $nameLen = strlen($name);
        if ($nameLen <= 2) {
            $masked = substr($name, 0, 1) . '*';
        } else {
            $masked = substr($name, 0, 1) . str_repeat('*', $nameLen - 2) . substr($name, -1);
        }
        return $masked . '@' . $domain;
    }
}
