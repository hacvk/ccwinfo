<?php

namespace app\common\model;

use think\Model;

/**
 * 推广内容模型
 */
class PromotionItem extends Model
{
    // 表名
    protected $name = 'promotion_item';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
        'review_status_text',
    ];

    public function getStatusList()
    {
        return [
            'draft'   => '草稿',
            'pending' => '待上线',
            'online'  => '投放中',
            'offline' => '已下线',
            'expired' => '已过期',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }

    public function getReviewStatusList()
    {
        return [
            'pending'  => '待审核',
            'approved' => '已通过',
            'rejected' => '已拒绝',
        ];
    }

    public function getReviewStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['review_status'] ?? '');
        $list = $this->getReviewStatusList();
        return $list[$value] ?? $value;
    }

    public function slot()
    {
        return $this->belongsTo(PromotionSlot::class, 'slot_id', 'id')->setEagerlyType(0);
    }

    public function category()
    {
        return $this->belongsTo(InfoCategory::class, 'category_id', 'id')->setEagerlyType(0);
    }
}
