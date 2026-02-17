<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略主表模型
 */
class Guide extends Model
{
    // 表名
    protected $name = 'guide';
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
     * attachments 读取器：JSON -> 逗号分隔
     */
    public function getAttachmentsAttr($value)
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
     * attachments 修改器：逗号分隔 -> JSON 存储
     */
    public function setAttachmentsAttr($value)
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
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo('GuideCategory', 'category_id', 'id')->setEagerlyType(0);
    }

    /**
     * 关联模型
     */
    public function guideModel()
    {
        return $this->belongsTo('GuideModel', 'model_id', 'id')->setEagerlyType(0);
    }

    /**
     * 关联录入管理员
     */
    public function admin()
    {
        return $this->belongsTo('app\admin\model\Admin', 'admin_id', 'id')->setEagerlyType(0);
    }
}

