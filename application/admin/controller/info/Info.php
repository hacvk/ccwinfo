<?php

namespace app\admin\controller\info;

use app\common\controller\Backend;
use app\common\model\InfoCategory;
use app\common\model\InfoField;
use app\common\model\InfoFieldValue;
use think\Db;

/**
 * 分类信息-信息管理
 *
 * @icon fa fa-file-text-o
 */
class Info extends Backend
{
    /**
     * @var \app\common\model\Info
     */
    protected $model = null;
    protected $searchFields = 'id,title,contact_name,contact_phone';
    protected $relationSearch = true;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\Info;

        // 状态列表
        $statusList = $this->model->getStatusList();
        $this->view->assign('statusList', $statusList);
        $this->assignconfig('statusList', $statusList);

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

            // 基础校验
            $this->validateInfoParams($params);

            // 录入管理员
            $params['entry_admin_id'] = (int)$this->auth->id;
            $params['user_id'] = 0;

            // 有效期计算
            $params['valid_until'] = $this->calcValidUntil(time(), (int)($params['valid_days'] ?? 0));

            // 获取自定义字段数据
            $extData = $this->request->post('ext/a', []);

            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->allowField(true)->save($params);
                if ($result !== false) {
                    $infoId = (int)$this->model->id;
                    // 保存自定义字段
                    $this->saveExtFields($infoId, (int)$params['category_id'], (int)($params['sub_category_id'] ?? 0), $extData);
                    // 生成快照
                    $this->updateExtSnapshot($infoId);
                }
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
        // 默认值
        $this->view->assign('row', [
            'status'     => 'published',
            'is_top'     => 0,
            'valid_days' => 30,
            'images'     => '',
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

            $this->validateInfoParams($params);

            // 有效期计算（以原创建时间为锚点）
            $anchorTime = $row['createtime'] ?: time();
            $params['valid_until'] = $this->calcValidUntil($anchorTime, (int)($params['valid_days'] ?? 0));

            $extData = $this->request->post('ext/a', []);

            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                // 保存自定义字段
                $this->saveExtFields((int)$row->id, (int)$params['category_id'], (int)($params['sub_category_id'] ?? 0), $extData);
                // 生成快照
                $this->updateExtSnapshot((int)$row->id);
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
        // 加载二级分类供编辑回显
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
     * AJAX: 根据分类获取自定义字段定义（含已有值）
     */
    public function getFields()
    {
        $categoryId = (int)$this->request->get('category_id', 0);
        $subCategoryId = (int)$this->request->get('sub_category_id', 0);
        $infoId = (int)$this->request->get('info_id', 0);

        if ($categoryId <= 0) {
            $this->success('', null, []);
        }

        // 获取字段定义
        $definitions = $this->getFieldDefinitions($categoryId, $subCategoryId);

        // 若编辑模式，获取已有值
        $valuesByFieldId = [];
        if ($infoId > 0 && !empty($definitions)) {
            $fieldIds = array_column($definitions, 'id');
            $rows = InfoFieldValue::where('info_id', $infoId)
                ->whereIn('field_id', $fieldIds)
                ->column('value', 'field_id');
            $valuesByFieldId = $rows;
        }

        foreach ($definitions as &$item) {
            $type = trim($item['type'] ?? 'text');
            // 解析 options
            $item['options'] = $this->decodeFieldOptions($item['options'] ?? '');
            // 回填值
            $value = $valuesByFieldId[$item['id']] ?? '';
            if ($type === 'checkbox' && $value !== '') {
                $item['value'] = explode(',', $value);
            } else {
                $item['value'] = $value;
            }
        }
        unset($item);

        $this->success('', null, $definitions);
    }

    // ================== 私有方法 ==================

    /**
     * 基础参数校验
     */
    private function validateInfoParams(&$params)
    {
        if (empty($params['category_id'])) {
            $this->error('请选择一级分类');
        }
        if (empty($params['sub_category_id'])) {
            $this->error('请选择二级分类');
        }
        if (empty($params['title'])) {
            $this->error('请填写标题');
        }
        if (empty($params['content'])) {
            $this->error('请填写内容');
        }
        if (empty($params['contact_name'])) {
            $params['contact_name'] = '管理员代录';
        }
        if (empty($params['contact_phone'])) {
            $this->error('请填写联系电话');
        }

        $params['category_id'] = (int)$params['category_id'];
        $params['sub_category_id'] = (int)($params['sub_category_id'] ?? 0);
        $params['district_id'] = (int)($params['district_id'] ?? 0);
        $params['is_top'] = empty($params['is_top']) ? 0 : 1;
        $params['valid_days'] = (int)($params['valid_days'] ?? 0);
        $params['send_notice'] = empty($params['send_notice']) ? 0 : 1;

        // price 处理
        $priceRaw = trim((string)($params['price'] ?? ''));
        $params['price'] = ($priceRaw !== '' && is_numeric($priceRaw)) ? (float)$priceRaw : null;
    }

    /**
     * 获取分类下的字段定义
     */
    private function getFieldDefinitions($categoryId, $subCategoryId)
    {
        $query = InfoField::where('status', 'normal')
            ->where('category_id', $categoryId);

        if ($subCategoryId > 0) {
            $query->where(function ($q) use ($subCategoryId) {
                $q->where('sub_category_id', 0)->whereOr('sub_category_id', $subCategoryId);
            });
        } else {
            $query->where('sub_category_id', 0);
        }

        return $query->order('weigh desc, id asc')
            ->field('id,name,key,type,required,options,placeholder')
            ->select()
            ->toArray();
    }

    /**
     * 保存自定义字段值
     */
    private function saveExtFields($infoId, $categoryId, $subCategoryId, $extData)
    {
        if (!is_array($extData)) {
            $extData = [];
        }

        // 获取字段定义
        $definitions = $this->getFieldDefinitions($categoryId, $subCategoryId);
        $defMap = [];
        foreach ($definitions as $def) {
            $key = trim($def['key'] ?? '');
            if ($key !== '') {
                $defMap[$key] = $def;
            }
        }

        // 先删除旧值
        InfoFieldValue::where('info_id', $infoId)->delete();

        if (empty($defMap) || empty($extData)) {
            return;
        }

        $rows = [];
        foreach ($extData as $key => $rawValue) {
            $fieldKey = trim((string)$key);
            if ($fieldKey === '' || !isset($defMap[$fieldKey])) {
                continue;
            }
            $def = $defMap[$fieldKey];
            $type = trim($def['type'] ?? 'text');
            $normalized = $this->normalizeFieldValue($def, $rawValue);
            if ($normalized['value'] === '') {
                continue;
            }

            $rows[] = [
                'info_id'      => $infoId,
                'field_id'     => (int)$def['id'],
                'key'          => $fieldKey,
                'value'        => $normalized['value'],
                'value_number' => $normalized['value_number'],
                'value_date'   => $normalized['value_date'],
            ];
        }

        if (!empty($rows)) {
            (new InfoFieldValue)->insertAll($rows);
        }
    }

    /**
     * 规范化字段值
     */
    private function normalizeFieldValue($definition, $rawValue)
    {
        $type = trim($definition['type'] ?? 'text');
        $options = $this->decodeFieldOptions($definition['options'] ?? '');

        $valueNumber = null;
        $valueDate = null;

        // checkbox 多选
        if ($type === 'checkbox') {
            $list = is_array($rawValue) ? $rawValue : [];
            $filtered = [];
            foreach ($list as $item) {
                $text = trim((string)$item);
                if ($text !== '') {
                    $filtered[] = $text;
                }
            }
            $value = implode(',', array_unique($filtered));
            return ['value' => $value, 'value_number' => null, 'value_date' => null];
        }

        // 标量值
        if (is_array($rawValue)) {
            $rawValue = reset($rawValue);
        }
        $value = is_scalar($rawValue) ? trim((string)$rawValue) : '';
        if ($value === '') {
            return ['value' => '', 'value_number' => null, 'value_date' => null];
        }

        if ($type === 'number' && is_numeric($value)) {
            $valueNumber = (float)$value;
        } elseif ($type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $valueDate = $value;
        }

        return ['value' => $value, 'value_number' => $valueNumber, 'value_date' => $valueDate];
    }

    /**
     * 生成/更新自定义字段快照
     */
    private function updateExtSnapshot($infoId)
    {
        $snapshot = [];
        $values = Db::name('info_field_value')
            ->alias('fv')
            ->join('__INFO_FIELD__ fd', 'fd.id = fv.field_id')
            ->where('fv.info_id', $infoId)
            ->where('fd.is_list_show', 1)
            ->field('fd.key,fd.name,fv.value')
            ->select();

        foreach ($values as $item) {
            $snapshot[$item['key']] = [
                'name'  => $item['name'],
                'value' => $item['value'],
            ];
        }

        $this->model->where('id', $infoId)->update([
            'ext_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * 计算有效期截止时间戳
     */
    private function calcValidUntil($anchorTime, $validDays)
    {
        if ($validDays <= 0) {
            return null; // 0=长期有效
        }
        return (int)$anchorTime + ($validDays * 86400);
    }

    /**
     * 解析字段选项
     */
    private function decodeFieldOptions($options)
    {
        if (is_array($options)) {
            return $options;
        }
        $text = trim((string)$options);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $list = preg_split('/\r\n|\r|\n|,/', $text);
        $result = [];
        foreach ($list as $item) {
            $v = trim((string)$item);
            if ($v !== '') {
                $result[] = $v;
            }
        }
        return array_values(array_unique($result));
    }
}
