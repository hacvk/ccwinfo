<?php

namespace app\common\model;

use think\Model;

/**
 * 单页内容模型
 */
class SinglePage extends Model
{
    protected $name = 'single_page';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $append = [
        'status_text',
    ];

    public function getStatusList()
    {
        return [
            'draft' => '草稿',
            'published' => '已发布',
            'offline' => '已下线',
        ];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[$value] ?? $value;
    }
}
