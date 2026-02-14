<?php

namespace app\common\model;

use think\Model;

/**
 * 短信发送日志模型
 */
class SmsLog extends Model
{
    protected $name = 'sms_log';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = false;

    protected $append = [
        'status_text',
    ];

    public function getStatusList()
    {
        return ['0' => __('失败'), '1' => __('成功')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?? ($data['success'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }
}
