<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\model\GuideModel as GuideModelEntity;

/**
 * 本地攻略模型管理
 *
 * @icon fa fa-cubes
 */
class Model extends Backend
{
    /**
     * @var \app\common\model\GuideModel
     */
    protected $model = null;
    protected $searchFields = 'id,name,key';
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new GuideModelEntity;
        $this->view->assign('statusList', $this->model->getStatusList());
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
}

