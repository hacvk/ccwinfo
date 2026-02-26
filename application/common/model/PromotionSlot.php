<?php

namespace app\common\model;

use think\Model;

/**
 * 推广展示位模型
 */
class PromotionSlot extends Model
{
    // 表名
    protected $name = 'promotion_slot';
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
            'text'       => '文字',
            'image'      => '图片',
            'mix'        => '图文混合',
            'friendlink' => '友情链接',
        ];
    }

    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? '';
    }

    /**
     * 将 config 字段解析为数组
     *
     * @return array
     */
    public function getConfigArray()
    {
        $raw = $this['config'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
