<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\model\GuideModel;
use think\Db;

/**
 * 本地攻略扩展字段管理
 *
 * @icon fa fa-puzzle-piece
 */
class Field extends Backend
{
    /**
     * @var \app\common\model\GuideField
     */
    protected $model = null;
    protected $searchFields = 'id,name,key';
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\GuideField;

        $typeList = $this->model->getTypeList();
        $modelList = GuideModel::where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');
        $this->view->assign('typeList', $typeList);
        $this->view->assign('modelList', $modelList);
        $this->assignconfig('typeList', $typeList);
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
            ->with(['model'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $row) {
            $row->getRelation('model');
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
            $params['options'] = $this->processOptions($params['options'] ?? '');
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
            $this->validateFieldKey($params, (int)$row['id']);

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

        $decoded = json_decode($row['options'], true);
        if (is_array($decoded)) {
            $row['options'] = implode("\n", $decoded);
        }
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 处理选项配置：换行/逗号 -> JSON 数组
     *
     * @param mixed $options
     * @return string
     */
    private function processOptions($options)
    {
        $text = trim((string)$options);
        if ($text === '') {
            return '';
        }
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
     * 校验字段 KEY
     *
     * @param array $params
     * @param int $excludeId
     * @return void
     */
    private function validateFieldKey(array $params, $excludeId = 0)
    {
        $key = trim((string)($params['key'] ?? ''));
        if ($key === '') {
            $this->error('字段KEY不能为空');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
            $this->error('字段KEY格式无效：必须以小写字母开头，仅允许小写字母、数字、下划线，长度1-64位');
        }
        $modelId = (int)($params['model_id'] ?? 0);
        if ($modelId < 1) {
            $this->error('请选择攻略模型');
        }

        $query = $this->model->where('model_id', $modelId)->where('key', $key);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        if ($query->find()) {
            $this->error('同一模型下字段KEY不能重复');
        }
    }
}

