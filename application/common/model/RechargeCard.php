<?php

namespace app\common\model;

use think\Model;

class RechargeCard extends Model
{
    protected $name = 'recharge_card';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $append = [
        'card_type_text',
        'status_text',
        'expiretime_text',
        'used_time_text',
    ];

    public function getCardTypeList()
    {
        return [
            'recharge' => __('Recharge card'),
            'discount' => __('Discount card'),
        ];
    }

    public function getCardTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['card_type'] ?? '');
        $list = $this->getCardTypeList();
        return $list[$value] ?? $value;
    }

    public function getStatusList()
    {
        return [
            'unused'   => __('Unused'),
            'reserved' => __('Reserved'),
            'issued'   => __('Issued'),
            'used'     => __('Used'),
            'disabled' => __('Disabled'),
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }

    public function getExpiretimeTextAttr($value, $data)
    {
        $value = $data['expiretime'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    public function getUsedTimeTextAttr($value, $data)
    {
        $value = $data['used_time'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    public function setExpiretimeAttr($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return is_numeric($value) ? (int)$value : strtotime($value);
    }
}
