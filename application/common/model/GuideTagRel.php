<?php

namespace app\common\model;

use think\Model;

/**
 * 本地攻略标签关系模型
 */
class GuideTagRel extends Model
{
    // 表名
    protected $name = 'guide_tag_rel';
    // 关闭自动写入时间戳
    protected $autoWriteTimestamp = false;
}

