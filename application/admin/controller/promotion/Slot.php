<?php

namespace app\admin\controller\promotion;

use app\common\controller\Backend;
use app\common\model\PromotionSlot as PromotionSlotModel;

/**
 * 推广展示位管理
 *
 * @icon fa fa-th-large
 */
class Slot extends Backend
{
    /**
     * @var \app\common\model\PromotionSlot
     */
    protected $model = null;

    protected $searchFields = 'id,code,name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new PromotionSlotModel;

        $this->view->assign('statusList', $this->model->getStatusList());
        $this->view->assign('typeList', $this->model->getTypeList());
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 删除
     *
     * 禁止删除系统预置展示位
     */
    public function del($ids = '')
    {
        $ids = $ids ?: $this->request->param('ids');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $ids = array_filter(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        $systemCount = $this->model->where('id', 'in', $ids)->where('is_system', 1)->count();
        if ($systemCount > 0) {
            $this->error('系统预置展示位不允许删除');
        }

        return parent::del(implode(',', $ids));
    }
}
