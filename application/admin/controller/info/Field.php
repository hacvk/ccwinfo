<?php

namespace app\admin\controller\info;

use app\common\controller\Backend;
use app\common\model\InfoCategory;
use think\Db;

/**
 * 分类信息-自定义字段管理
 *
 * @icon fa fa-puzzle-piece
 */
class Field extends Backend
{
    /**
     * @var \app\common\model\InfoField
     */
    protected $model = null;
    protected $searchFields = 'id,name,key';
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\InfoField;

        // 字段类型列表
        $typeList = $this->model->getTypeList();
        $this->view->assign('typeList', $typeList);
        $this->assignconfig('typeList', $typeList);

        // 一级分类列表
        $categoryList = InfoCategory::where('pid', 0)->where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');
        $this->view->assign('categoryList', $categoryList);
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
            ->with(['category', 'subcategory'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $row) {
            $row->getRelation('category');
            $row->getRelation('subcategory');
        }

        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if (empty($params)) {
                $this->error(__('Parameter %s can not be empty', ''));
            }
            $params = $this->preExcludeFields($params);
            // 处理 options 字段
            $params['options'] = $this->processOptions($params['options'] ?? '');
            // KEY 格式校验
            $this->validateFieldKey($params);

            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->allowField(true)->save($params);
                Db::commit();
            } catch (\think\exception\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result === false) {
                $this->error(__('No rows were inserted'));
            }
            $this->success();
        }
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
            $params['options'] = $this->processOptions($params['options'] ?? '');
            $this->validateFieldKey($params, $row['id']);

            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                Db::commit();
            } catch (\think\exception\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result === false) {
                $this->error(__('No rows were updated'));
            }
            $this->success();
        }
        // 编辑时将 JSON options 美化为多行文本
        $decoded = json_decode($row['options'], true);
        if (is_array($decoded)) {
            $row['options'] = implode("\n", $decoded);
        }
        // 加载二级分类供前端回显
        $subCategoryList = [];
        if ($row['category_id']) {
            $subCategoryList = InfoCategory::where('pid', $row['category_id'])->where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');
        }
        $this->view->assign('subCategoryList', $subCategoryList);
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * AJAX: 根据一级分类获取二级分类
     */
    public function getSubcategory()
    {
        $pid = (int)$this->request->get('pid', 0);
        $list = InfoCategory::where('pid', $pid)->where('status', 'normal')->order('weigh desc, id asc')->field('id, name')->select();
        $this->success('', null, $list);
    }

    /**
     * 处理选项配置：换行/逗号 -> JSON 数组
     */
    private function processOptions($options)
    {
        $text = trim((string)$options);
        if ($text === '') {
            return '';
        }
        // 如果已经是 JSON 数组则直接返回
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $list = $decoded;
        } else {
            $list = preg_split('/\r\n|\r|\n|,/', $text);
        }
        $result = [];
        foreach ($list as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $result[] = $value;
            }
        }
        $result = array_values(array_unique($result));
        return !empty($result) ? json_encode($result, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * 校验字段 KEY 格式及唯一性
     */
    private function validateFieldKey($params, $excludeId = 0)
    {
        $key = trim($params['key'] ?? '');
        if ($key === '') {
            $this->error('字段KEY不能为空');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
            $this->error('字段KEY格式无效：必须以小写字母开头，仅允许小写字母、数字、下划线，长度1-64位');
        }
        $categoryId = (int)($params['category_id'] ?? 0);
        if ($categoryId < 1) {
            $this->error('请选择一级分类');
        }
        // 检查同一分类下 KEY 唯一
        $query = $this->model->where('category_id', $categoryId)->where('key', $key);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        if ($query->find()) {
            $this->error('同一一级分类下字段KEY不能重复');
        }
    }
}
