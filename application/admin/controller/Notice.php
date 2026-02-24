<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use app\common\library\AliyunSms;
use app\common\model\Sms;
use app\common\model\SmsLog;
use think\Db;
use think\Session;

/**
 * 通知中心管理
 */
class Notice extends Backend
{
    protected $model = null;
    protected $noNeedRight = [''];
    protected $smsFields = null;

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
        if (!in_array($tab, ['logs', 'otp', 'aliyun'], true)) {
            $tab = 'logs';
        }
        $this->assign('tab', $tab);
        $this->assign('isSuper', $this->auth->isSuperAdmin());
        $this->assign('otpDays', 30);
        $this->assign('smsDays', 180);
        
        if ($tab === 'aliyun') {
            $config = [
                'aliyun_sms_access_key_id' => \think\Config::get('site.aliyun_sms_access_key_id') ?: '',
                'aliyun_sms_access_key_secret' => \think\Config::get('site.aliyun_sms_access_key_secret') ?: '',
                'aliyun_sms_sign_name' => \think\Config::get('site.aliyun_sms_sign_name') ?: '',
                'aliyun_sms_region_id' => \think\Config::get('site.aliyun_sms_region_id') ?: 'cn-hangzhou',
                'aliyun_sms_endpoint' => \think\Config::get('site.aliyun_sms_endpoint') ?: 'dysmsapi.aliyuncs.com',
            ];
            $this->assign('row', $config);
        }
        
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
        return $this->view->fetch('index');
    }

    /**
     * 验证码记录
     */
    public function otp()
    {
        $this->model = new Sms();
        
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            $status = trim((string)$this->request->get('status', ''));
            $now = time();
            $hasUsedAt = $this->hasSmsField('used_at');
            $hasExpireAt = $this->hasSmsField('expire_at');
            
            $query = $this->model->where($where);
            
            if ($status === 'used') {
                if ($hasUsedAt) {
                    $query->where('used_at', '>', 0);
                } else {
                    $query->where('id', 0);
                }
            } elseif ($status === 'unused') {
                if ($hasUsedAt && $hasExpireAt) {
                    $query->where('used_at', 0)->where('expire_at', '>', $now);
                } elseif ($hasUsedAt) {
                    $query->where('used_at', 0)->where('createtime', '>', $now - 600);
                } elseif ($hasExpireAt) {
                    $query->where('expire_at', '>', $now);
                } else {
                    $query->where('createtime', '>', $now - 600);
                }
            } elseif ($status === 'expired') {
                if ($hasUsedAt && $hasExpireAt) {
                    $query->where('used_at', 0)->where('expire_at', '<=', $now);
                } elseif ($hasUsedAt) {
                    $query->where('used_at', 0)->where('createtime', '<=', $now - 600);
                } elseif ($hasExpireAt) {
                    $query->where('expire_at', '<=', $now);
                } else {
                    $query->where('createtime', '<=', $now - 600);
                }
            }
            
            $list = $query->order($sort, $order)->paginate($limit);

            $isSuper = $this->auth->isSuperAdmin();
            foreach ($list as &$row) {
                $usedAt = $hasUsedAt ? (int)($row['used_at'] ?? 0) : 0;
                $expireAt = $hasExpireAt ? (int)($row['expire_at'] ?? 0) : ((int)($row['createtime'] ?? 0) + 600);
                if ($expireAt <= 0) {
                    $expireAt = null;
                }

                if (!$isSuper) {
                    $row['mobile'] = AliyunSms::maskPhone((string)$row['mobile']);
                    $row['code'] = AliyunSms::maskCode((string)$row['code']);
                }

                $row['used_at'] = $usedAt > 0 ? $usedAt : null;
                $row['expire_at'] = $expireAt;

                if ($usedAt > 0) {
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
        return $this->view->fetch('index');
    }

    protected function getSmsFields()
    {
        if (is_array($this->smsFields)) {
            return $this->smsFields;
        }

        $this->smsFields = [];
        try {
            $prefix = (string)\think\Config::get('database.prefix');
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
                'aliyun_sms_access_key_id' => $params['aliyun_sms_access_key_id'] ?? '',
                'aliyun_sms_access_key_secret' => $params['aliyun_sms_access_key_secret'] ?? '',
                'aliyun_sms_sign_name' => $params['aliyun_sms_sign_name'] ?? '',
                'aliyun_sms_region_id' => $params['aliyun_sms_region_id'] ?? 'cn-hangzhou',
                'aliyun_sms_endpoint' => $params['aliyun_sms_endpoint'] ?? 'dysmsapi.aliyuncs.com',
            ];

            foreach ($configData as $name => $value) {
                Db::name('config')->where('name', $name)->update(['value' => $value]);
            }

            $this->success('保存成功');
        }

        $config = [
            'aliyun_sms_access_key_id' => \think\Config::get('site.aliyun_sms_access_key_id') ?: '',
            'aliyun_sms_access_key_secret' => \think\Config::get('site.aliyun_sms_access_key_secret') ?: '',
            'aliyun_sms_sign_name' => \think\Config::get('site.aliyun_sms_sign_name') ?: '',
            'aliyun_sms_region_id' => \think\Config::get('site.aliyun_sms_region_id') ?: 'cn-hangzhou',
            'aliyun_sms_endpoint' => \think\Config::get('site.aliyun_sms_endpoint') ?: 'dysmsapi.aliyuncs.com',
        ];

        $this->assign('row', $config);
        return $this->view->fetch();
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
            'aliyun_sms_access_key_id' => $this->request->post('aliyun_sms_access_key_id') ?: \think\Config::get('site.aliyun_sms_access_key_id'),
            'aliyun_sms_access_key_secret' => $this->request->post('aliyun_sms_access_key_secret') ?: \think\Config::get('site.aliyun_sms_access_key_secret'),
            'aliyun_sms_sign_name' => $this->request->post('aliyun_sms_sign_name') ?: \think\Config::get('site.aliyun_sms_sign_name'),
            'aliyun_sms_region_id' => $this->request->post('aliyun_sms_region_id') ?: \think\Config::get('site.aliyun_sms_region_id') ?: 'cn-hangzhou',
            'aliyun_sms_endpoint' => $this->request->post('aliyun_sms_endpoint') ?: \think\Config::get('site.aliyun_sms_endpoint') ?: 'dysmsapi.aliyuncs.com',
        ];

        if (empty($config['aliyun_sms_access_key_id']) || empty($config['aliyun_sms_access_key_secret'])) {
            $this->error('请先配置 AccessKey ID 和 Secret');
        }
        if (empty($config['aliyun_sms_sign_name'])) {
            $this->error('请先配置短信签名');
        }

        $code = mt_rand(100000, 999999);
        $templateCode = 'SMS_123456789';

        $result = AliyunSms::send($phone, $templateCode, ['code' => (string)$code], 'test');

        if ($result['success']) {
            $this->success('短信发送成功，验证码：' . $code);
        } else {
            $this->error('短信发送失败：' . ($result['error'] ?? '未知错误'));
        }
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
}
