<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略扩展字段值模型
 */
class GuideExtValue extends Model
{
    // 表名
    protected $name = 'guide_ext_value';
    // 关闭自动写入时间戳
    protected $autoWriteTimestamp = false;
}

