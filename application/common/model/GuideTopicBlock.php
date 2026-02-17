<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略专题区块模型
 */
class GuideTopicBlock extends Model
{
    // 表名
    protected $name = 'guide_topic_block';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
        'type_text',
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

    public function getTypeList()
    {
        return [
            'manual_list'    => '手选攻略',
            'rule_list'      => '规则聚合',
            'external_cards' => '外链卡片',
        ];
    }

    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? $value;
    }
}

