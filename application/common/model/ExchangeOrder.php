<?php

namespace app\common\model;

use think\Model;

class ExchangeOrder extends Model
{
    protected $name = 'exchange_order';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $append = [
        'order_status_text',
        'points_status_text',
        'deliver_time_text',
        'cancel_time_text',
    ];

    public function getOrderStatusList()
    {
        return [
            'pending'   => __('Pending'),
            'delivered' => __('Delivered'),
            'shipped'   => __('Shipped'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            'rejected'  => __('Rejected'),
        ];
    }

    public function getOrderStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['order_status'] ?? '');
        $list = $this->getOrderStatusList();
        return $list[$value] ?? $value;
    }

    public function getPointsStatusList()
    {
        return [
            'undeducted' => __('Undeducted'),
            'deducted'   => __('Deducted'),
            'refunded'   => __('Refunded'),
        ];
    }

    public function getPointsStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['points_status'] ?? '');
        $list = $this->getPointsStatusList();
        return $list[$value] ?? $value;
    }

    public function goods()
    {
        return $this->belongsTo(ExchangeGoods::class, 'goods_id', 'id')->setEagerlyType(0);
    }

    public function getDeliverTimeTextAttr($value, $data)
    {
        $value = $data['deliver_time'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }

    public function getCancelTimeTextAttr($value, $data)
    {
        $value = $data['cancel_time'] ?? null;
        return $value ? date('Y-m-d H:i:s', (int)$value) : '';
    }
}
