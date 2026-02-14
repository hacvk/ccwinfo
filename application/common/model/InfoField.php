<?php

namespace app\common\model;

use think\Model;

/**
 * 分类信息-自定义字段定义模型
 */
class InfoField extends Model
{
    // 表名
    protected $name = 'info_field';
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

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $row->save(['weigh' => $row['id']]);
            }
        });
    }

    /**
     * 字段类型列表
     */
    public function getTypeList()
    {
        return [
            'text'     => '文本',
            'number'   => '数字',
            'date'     => '日期',
            'select'   => '下拉选择',
            'radio'    => '单选',
            'checkbox' => '多选',
            'textarea' => '多行文本',
        ];
    }

    public function getTypeTextAttr($value, $data)
    {
        $value = $value ?: ($data['type'] ?? '');
        $list = $this->getTypeList();
        return $list[$value] ?? $value;
    }

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

    /**
     * 关联一级分类
     */
    public function category()
    {
        return $this->belongsTo('InfoCategory', 'category_id', 'id')->setEagerlyType(0);
    }

    /**
     * 关联二级分类
     */
    public function subcategory()
    {
        return $this->belongsTo('InfoCategory', 'sub_category_id', 'id')->setEagerlyType(0);
    }
}
