<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略专题模型
 */
class GuideTopic extends Model
{
    // 表名
    protected $name = 'guide_topic';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
    ];

    public function getStatusList()
    {
        return [
            'draft'     => '草稿',
            'published' => '已发布',
            'offline'   => '已下线',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }
}

