<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\model\GuideTag as GuideTagModel;

/**
 * 本地攻略标签管理
 *
 * @icon fa fa-tags
 */
class Tag extends Backend
{
    /**
     * @var \app\common\model\GuideTag
     */
    protected $model = null;
    protected $searchFields = 'id,name,slug';
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new GuideTagModel;
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

    /**
     * Selectpage 标签联想
     */
    public function selectpage()
    {
        $q = trim((string)$this->request->request('q_word', $this->request->request('q', '')));
        $page = (int)$this->request->request('pageNumber', $this->request->request('page', 1));
        $pageSize = (int)$this->request->request('pageSize', $this->request->request('rows', 20));
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));

        $query = $this->model->where('status', 'normal');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->whereOr('slug', 'like', "%{$q}%");
            });
        }

        $total = (int)$query->count();
        $list = $query->field('id,name,slug,use_count')
            ->order('use_count desc, id desc')
            ->page($page, $pageSize)
            ->select();
        $rows = is_object($list) ? $list->toArray() : (array)$list;

        return json(['total' => $total, 'rows' => $rows]);
    }
}

