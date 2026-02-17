<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略专题区块条目模型
 */
class GuideTopicBlockItem extends Model
{
    // 表名
    protected $name = 'guide_topic_block_item';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
        'item_type_text',
    ];

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? '';
    }

    public function getItemTypeList()
    {
        return [
            'guide'    => '攻略',
            'external' => '外链',
        ];
    }

    public function getItemTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['item_type'] ?? '');
        $list = $this->getItemTypeList();
        return $list[$value] ?? $value;
    }
}

