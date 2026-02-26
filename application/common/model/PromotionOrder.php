<?php

namespace app\common\model;

use think\Model;

/**
 * 推广订单模型
 */
class PromotionOrder extends Model
{
    // 表名
    protected $name = 'promotion_order';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'order_status_text',
        'pay_status_text',
        'pay_type_text',
    ];

    public function getOrderStatusList()
    {
        return [
            'pending'   => '待处理',
            'approved'  => '已通过',
            'rejected'  => '已拒绝',
            'cancelled' => '已取消',
            'finished'  => '已完成',
        ];
    }

    public function getOrderStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['order_status'] ?? '');
        $list = $this->getOrderStatusList();
        return $list[$value] ?? $value;
    }

    public function getPayStatusList()
    {
        return [
            'unpaid'       => '未支付',
            'paid'         => '已支付',
            'offline_paid' => '线下已付',
            'cancelled'    => '已取消',
        ];
    }

    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['pay_status'] ?? '');
        $list = $this->getPayStatusList();
        return $list[$value] ?? $value;
    }

    public function getPayTypeList()
    {
        return [
            'wechat'  => '微信支付',
            'alipay'  => '支付宝',
            'offline' => '线下支付',
        ];
    }

    public function getPayTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['pay_type'] ?? '');
        $list = $this->getPayTypeList();
        return $list[$value] ?? ($value ?: '-');
    }

    public function item()
    {
        return $this->belongsTo(PromotionItem::class, 'item_id', 'id')->setEagerlyType(0);
    }
}
