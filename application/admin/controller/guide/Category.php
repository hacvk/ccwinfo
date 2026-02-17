<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\model\GuideCategory as GuideCategoryModel;
use fast\Tree;

/**
 * 本地攻略分类管理
 *
 * @icon fa fa-list
 */
class Category extends Backend
{
    /**
     * @var \app\common\model\GuideCategory
     */
    protected $model = null;
    protected $categorylist = [];
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new GuideCategoryModel;

        $tree = Tree::instance();
        $tree->init(collection($this->model->order('weigh desc,id asc')->select())->toArray(), 'pid');
        $this->categorylist = $tree->getTreeList($tree->getTreeArray(0), 'name');
        $categorydata = [0 => ['type' => 'all', 'name' => __('None')]];
        foreach ($this->categorylist as $v) {
            $categorydata[$v['id']] = $v;
        }
        $this->view->assign("parentList", $categorydata);
    }

    /**
     * 查看
     */
    public function index()
    {
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $list = $this->categorylist;
            $result = ["total" => count($list), "rows" => $list];
            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
        }
        return parent::add();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }

        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                if ($params['pid'] != $row['pid']) {
                    $childrenIds = Tree::instance()->init(collection(GuideCategoryModel::select())->toArray(), 'pid')->getChildrenIds($row['id'], true);
                    if (in_array($params['pid'], $childrenIds)) {
                        $this->error(__('Can not change the parent to child or itself'));
                    }
                }

                try {
                    $result = $row->allowField(true)->save($params);
                    if ($result !== false) {
                        $this->success();
                    }
                    $this->error($row->getError());
                } catch (\think\exception\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (\think\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    /**
     * Selectpage搜索
     *
     * @internal
     */
    public function selectpage()
    {
        return parent::selectpage();
    }
}

