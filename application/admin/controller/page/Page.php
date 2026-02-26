<?php

namespace app\admin\controller\page;

use app\common\controller\Backend;
use app\common\model\SinglePage as SinglePageModel;
use fast\Tree;

/**
 * 单页管理
 *
 * @icon fa fa-file-text-o
 */
class Page extends Backend
{
    /**
     * @var SinglePageModel
     */
    protected $model = null;

    protected $searchFields = 'id,title,slug,external_url';
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new SinglePageModel();

        $statusList = $this->model->getStatusList();
        $isGroupList = ['0' => '内容页', '1' => '分组页'];

        $this->assignconfig('statusList', $statusList);
        $this->view->assign('statusList', $statusList);
        $this->view->assign('isGroupList', $isGroupList);
    }

    /**
     * 列表
     */
    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if (!$this->request->isAjax()) {
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

        $rows = $list->items();
        $parentIds = [];
        foreach ($rows as $row) {
            $pid = (int)($row['parent_id'] ?? 0);
            if ($pid > 0) {
                $parentIds[] = $pid;
            }
        }
        $parentMap = [];
        if (!empty($parentIds)) {
            $parentIds = array_values(array_unique($parentIds));
            $parentMap = $this->model
                ->where('id', 'in', $parentIds)
                ->column('title', 'id');
            $parentMap = is_array($parentMap) ? $parentMap : [];
        }

        foreach ($rows as &$row) {
            $pid = (int)($row['parent_id'] ?? 0);
            $row['parent_title'] = $pid > 0 ? (string)($parentMap[$pid] ?? '-') : '顶级';
            $row['is_group_text'] = (int)$row['is_group'] === 1 ? '分组页' : '内容页';
        }
        unset($row);

        return json(['total' => $list->total(), 'rows' => $rows]);
    }

    /**
     * 新增
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            $params = $this->prepareRowParams($params, null);

            $result = $this->model->allowField(true)->save($params);
            if ($result === false) {
                $this->error(__('No rows were inserted'));
            }
            $this->success();
        }

        $this->view->assign('parentList', $this->buildParentList(0));
        $this->view->assign('row', [
            'parent_id' => 0,
            'is_group' => 0,
            'title' => '',
            'slug' => '',
            'slug_locked' => 0,
            'cover' => '',
            'content' => '',
            'external_url' => '',
            'show_in_footer' => 0,
            'status' => 'draft',
            'weigh' => 0,
            'views' => 0,
        ]);
        return $this->view->fetch();
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

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            $params = $this->prepareRowParams($params, $row);

            $result = $row->allowField(true)->save($params);
            if ($result === false) {
                $this->error(__('No rows were updated'));
            }
            $this->success();
        }

        $rowData = is_callable([$row, 'toArray']) ? $row->toArray() : (array)$row;
        $rowData['slug'] = (string)($rowData['slug'] ?? '');
        $rowData['cover'] = (string)($rowData['cover'] ?? '');
        $rowData['content'] = (string)($rowData['content'] ?? '');
        $rowData['external_url'] = (string)($rowData['external_url'] ?? '');

        $this->view->assign('parentList', $this->buildParentList((int)$row['id']));
        $this->view->assign('row', $rowData);
        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = '')
    {
        $ids = $ids ?: $this->request->post('ids', '');
        if (!$ids) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            $this->error(__('Parameter %s can not be empty', 'ids'));
        }

        $rows = $this->model->where('id', 'in', $ids)->select();
        if (!$rows || count($rows) < 1) {
            $this->error(__('No Results were found'));
        }

        foreach ($rows as $row) {
            $childCount = (int)$this->model->where('parent_id', (int)$row['id'])->count();
            if ($childCount > 0) {
                $this->error('页面“' . (string)$row['title'] . '”存在子页面，不能删除');
            }
        }

        $deleted = $this->model->where('id', 'in', $ids)->delete();
        if (!$deleted) {
            $this->error(__('No rows were deleted'));
        }
        $this->success();
    }

    /**
     * 构建上级页面下拉数据
     *
     * @param int $excludeId
     * @return array
     */
    protected function buildParentList($excludeId = 0)
    {
        $rows = $this->model
            ->field('id,parent_id,title,is_group,weigh')
            ->order('weigh desc,id asc')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;

        $excludeIds = [];
        if ($excludeId > 0) {
            $excludeIds = $this->collectDescendantIds($excludeId, $rows, true);
            $excludeIds = array_flip($excludeIds);
        }

        $treeSource = [];
        foreach ($rows as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (isset($excludeIds[$id])) {
                continue;
            }
            $treeSource[] = $item;
        }

        $tree = Tree::instance();
        $tree->init($treeSource, 'parent_id');
        $treeList = $tree->getTreeList($tree->getTreeArray(0), 'title');

        $parentList = [0 => '顶级'];
        foreach ($treeList as $item) {
            if ((int)($item['is_group'] ?? 0) !== 1) {
                continue;
            }
            $parentList[(int)$item['id']] = (string)$item['title'];
        }
        return $parentList;
    }

    /**
     * 参数归一化与业务校验
     *
     * @param array $params
     * @param SinglePageModel|null $row
     * @return array
     */
    protected function prepareRowParams(array $params, ?SinglePageModel $row = null)
    {
        $statusList = $this->model->getStatusList();

        $params['title'] = trim((string)($params['title'] ?? ''));
        $params['parent_id'] = (int)($params['parent_id'] ?? 0);
        $params['is_group'] = !empty($params['is_group']) ? 1 : 0;
        $params['slug'] = strtolower(trim((string)($params['slug'] ?? '')));
        $params['cover'] = trim((string)($params['cover'] ?? ''));
        $params['content'] = (string)($params['content'] ?? '');
        $params['external_url'] = trim((string)($params['external_url'] ?? ''));
        $params['show_in_footer'] = !empty($params['show_in_footer']) ? 1 : 0;
        $params['weigh'] = (int)($params['weigh'] ?? 0);
        $params['views'] = max(0, (int)($params['views'] ?? 0));

        $status = trim((string)($params['status'] ?? 'draft'));
        $params['status'] = isset($statusList[$status]) ? $status : 'draft';

        if ($params['title'] === '') {
            $this->error('请填写页面标题');
        }

        if ($params['parent_id'] > 0) {
            $parent = $this->model->where('id', $params['parent_id'])->find();
            if (!$parent) {
                $this->error('上级页面不存在');
            }
            if ((int)$parent['is_group'] !== 1) {
                $this->error('上级页面必须是分组页');
            }
        }

        if ($row) {
            $id = (int)$row['id'];
            if ($params['parent_id'] === $id) {
                $this->error('上级页面不能选择自己');
            }

            if ($params['parent_id'] > 0) {
                $cursor = $params['parent_id'];
                $guard = 0;
                while ($cursor > 0 && $guard < 1000) {
                    if ($cursor === $id) {
                        $this->error('不能将上级页面设置为自身的子节点');
                    }
                    $parent = $this->model->where('id', $cursor)->field('id,parent_id')->find();
                    if (!$parent) {
                        break;
                    }
                    $cursor = (int)$parent['parent_id'];
                    $guard++;
                }
            }

            if ((int)$row['is_group'] === 1 && $params['is_group'] === 0) {
                $childCount = (int)$this->model->where('parent_id', $id)->count();
                if ($childCount > 0) {
                    $this->error('当前分组页存在子页面，不能改为内容页');
                }
            }
        }

        if ($params['is_group'] === 1) {
            $params['slug'] = null;
            $params['cover'] = '';
            $params['content'] = '';
            $params['external_url'] = '';
            $params['show_in_footer'] = 0;
            $params['views'] = 0;
        } else {
            if ($params['slug'] === '') {
                $this->error('请填写页面 URL 标识');
            }
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $params['slug'])) {
                $this->error('URL 标识格式不正确，仅允许小写字母、数字和中划线，长度 2-63');
            }
            if ($params['external_url'] !== '' && filter_var($params['external_url'], FILTER_VALIDATE_URL) === false) {
                $this->error('外链地址格式不正确');
            }
        }

        $currentlyLocked = false;
        if ($row) {
            $currentlyLocked = ((int)$row['slug_locked'] === 1) || ((string)$row['status'] === 'published');
            if ($currentlyLocked && (int)$row['is_group'] === 0 && $params['is_group'] === 1) {
                $this->error('已发布的内容页不允许改为分组页');
            }
            if (
                $params['is_group'] === 0
                && $currentlyLocked
                && $params['slug'] !== strtolower(trim((string)($row['slug'] ?? '')))
            ) {
                $this->error('已发布页面的 URL 标识不允许修改');
            }
        }

        if ($params['is_group'] === 0) {
            $slugQuery = $this->model->where('slug', $params['slug']);
            if ($row) {
                $slugQuery->where('id', '<>', (int)$row['id']);
            }
            if ($slugQuery->find()) {
                $this->error('URL 标识已存在，请更换');
            }
        }

        $params['slug_locked'] = 0;
        if ($params['is_group'] === 0 && ($params['status'] === 'published' || $currentlyLocked)) {
            $params['slug_locked'] = 1;
        }

        return $params;
    }

    /**
     * 获取某节点的后代 ID（可包含自身）
     *
     * @param int $id
     * @param array $rows
     * @param bool $includeSelf
     * @return array
     */
    protected function collectDescendantIds($id, array $rows, $includeSelf = true)
    {
        $id = (int)$id;
        if ($id < 1) {
            return [];
        }

        $childrenMap = [];
        foreach ($rows as $row) {
            $parentId = (int)($row['parent_id'] ?? 0);
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId < 1) {
                continue;
            }
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $rowId;
        }

        $result = [];
        $stack = [$id];
        while (!empty($stack)) {
            $current = (int)array_pop($stack);
            if ($current < 1 || isset($result[$current])) {
                continue;
            }
            $result[$current] = true;
            foreach (($childrenMap[$current] ?? []) as $childId) {
                $childId = (int)$childId;
                if ($childId > 0 && !isset($result[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        if (!$includeSelf) {
            unset($result[$id]);
        }

        return array_values(array_map('intval', array_keys($result)));
    }
}
