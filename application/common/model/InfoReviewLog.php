<?php

namespace app\common\model;

use think\Model;

/**
 * 分类信息-审核日志模型
 */
class InfoReviewLog extends Model
{
    // 表名
    protected $name = 'info_review_log';
    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
}
