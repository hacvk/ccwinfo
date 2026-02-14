<?php

namespace app\common\model;

use think\Model;

/**
 * 分类信息-自定义字段值模型
 */
class InfoFieldValue extends Model
{
    // 表名
    protected $name = 'info_field_value';
    // 关闭自动写入时间戳
    protected $autoWriteTimestamp = false;
}
