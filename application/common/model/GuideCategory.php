<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略分类模型
 */
class GuideCategory extends Model
{
    // 表名
    protected $name = 'guide_category';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    // 追加属性
    protected $append = [
        'status_text',
    ];

    protected static function init()
    {
        self::afterInsert(function ($row) {
            if (!$row['weigh']) {
                $row->save(['weigh' => $row['id']]);
            }
        });
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
     * 获取分类列表
     *
     * @param int|null $pid
     * @return array
     */
    public static function getCategoryList($pid = null)
    {
        $where = ['status' => 'normal'];
        if (!is_null($pid)) {
            $where['pid'] = (int)$pid;
        }
        return collection(self::where($where)->order('weigh desc, id asc')->select())->toArray();
    }
}

