<?php

namespace app\common\model;

use think\Model;

class ExchangeGoods extends Model
{
    protected $name = 'exchange_goods';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $append = [
        'goods_type_text',
        'virtual_mode_text',
        'virtual_source_text',
        'status_text',
        'start_time_text',
        'end_time_text',
    ];

    public function getGoodsTypeList()
    {
        return [
            'physical' => __('Physical'),
            'virtual'  => __('Virtual'),
        ];
    }

    public function getGoodsTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['goods_type'] ?? '');
        $list = $this->getGoodsTypeList();
        return $list[$value] ?? $value;
    }

    public function getVirtualModeList()
    {
        return [
            'internal_recharge_card' => __('Internal recharge card'),
            'internal_discount_card' => __('Internal discount card'),
            'manual'                 => __('Manual deliver'),
        ];
    }

    public function getVirtualModeTextAttr($value, $data)
    {
        $value = $value ?: ($data['virtual_mode'] ?? '');
        $list = $this->getVirtualModeList();
        return $list[$value] ?? ($value ?: '-');
    }

    public function getVirtualSourceList()
    {
        return [
            'inside'  => __('Inside'),
            'outside' => __('Outside'),
        ];
    }

    public function getVirtualSourceTextAttr($value, $data)
    {
        $value = $value ?: ($data['virtual_source'] ?? '');
        $list = $this->getVirtualSourceList();
        return $list[$value] ?? ($value ?: '-');
    }

    public function getStatusList()
    {
        return [
            'normal' => __('Normal'),
            'hidden' => __('Hidden'),
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }

    public function getStartTimeTextAttr($value, $data)
    {
        $value = $data['start_time'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    public function getEndTimeTextAttr($value, $data)
    {
        $value = $data['end_time'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    public function setStartTimeAttr($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return is_numeric($value) ? (int)$value : strtotime($value);
    }

    public function setEndTimeAttr($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return is_numeric($value) ? (int)$value : strtotime($value);
    }
}
