<?php

namespace app\common\model;

use think\Model;

/**
 * 分类信息-信息主表模型
 */
class Info extends Model
{
    // 表名
    protected $name = 'info';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
    ];

    /**
     * 状态列表
     */
    public function getStatusList()
    {
        return [
            'draft'     => '草稿',
            'pending'   => '待审核',
            'published' => '已发布',
            'rejected'  => '已拒绝',
            'expired'   => '已过期',
            'revoked'   => '已撤销',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }

    /**
     * images 读取器：JSON -> 逗号分隔（FastAdmin 多图展示）
     */
    public function getImagesAttr($value)
    {
        if (empty($value)) {
            return '';
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode(',', $decoded);
        }
        return $value;
    }

    /**
     * images 修改器：逗号分隔 -> JSON 存储
     */
    public function setImagesAttr($value)
    {
        if (empty($value)) {
            return json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if (is_array($value)) {
            $list = $value;
        } else {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $list = $decoded;
            } else {
                $list = array_filter(array_map('trim', explode(',', $value)), function ($v) {
                    return $v !== '';
                });
            }
        }
        return json_encode(array_values($list), JSON_UNESCAPED_UNICODE);
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

    /**
     * 关联录入管理员
     */
    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'entry_admin_id', 'id')->setEagerlyType(0);
    }
}
