<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\library\GuideTagService;
use app\common\model\GuideCategory;
use app\common\model\GuideExtValue;
use app\common\model\GuideField;
use app\common\model\GuideModel;
use app\common\model\GuideTopicBlockItem;
use think\Db;

/**
 * 本地攻略管理
 *
 * @icon fa fa-map-signs
 */
class Guide extends Backend
{
    /**
     * @var \app\common\model\Guide
     */
    protected $model = null;
    protected $searchFields = 'id,title,keywords,summary';
    protected $tagService = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\Guide;
        $this->tagService = new GuideTagService();

        $statusList = $this->model->getStatusList();
        $categoryList = GuideCategory::where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');
        $modelList = GuideModel::where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');

        $this->view->assign('statusList', $statusList);
        $this->view->assign('categoryList', $categoryList);
        $this->view->assign('modelList', $modelList);
        $this->assignconfig('statusList', $statusList);
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
            ->alias('g')
            ->join('__GUIDE_CATEGORY__ c', 'c.id = g.category_id', 'LEFT')
            ->join('__GUIDE_MODEL__ m', 'm.id = g.model_id', 'LEFT')
            ->where($where)
            ->field('g.*,c.name AS category_name,m.name AS model_name')
            ->order($sort, $order)
            ->paginate($limit);

        $rows = $list->items();
        foreach ($rows as &$row) {
            $row['tag_names'] = implode(',', $this->tagService->getGuideTagNames((int)$row['id']));
        }
        unset($row);

        $result = ['total' => $list->total(), 'rows' => $rows];
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
            $tagText = $params['tags'] ?? '';
            unset($params['tags']);
            $extData = $this->request->post('ext/a', []);
            $this->validateGuideParams($params);

            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->allowField(true)->save($params);
                if ($result !== false) {
                    $guideId = (int)$this->model->id;
                    $this->saveExtFields($guideId, (int)$params['model_id'], $extData);
                    $this->tagService->syncGuideTags($guideId, $tagText);
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

        $this->view->assign('row', [
            'status'       => 'draft',
            'is_top'       => 0,
            'is_external'  => 0,
            'publish_time' => date('Y-m-d H:i:s'),
            'images'       => '',
            'attachments'  => '',
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
            $tagText = $params['tags'] ?? '';
            unset($params['tags']);
            $extData = $this->request->post('ext/a', []);
            $this->validateGuideParams($params);

            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                $this->saveExtFields((int)$row['id'], (int)$params['model_id'], $extData);
                $this->tagService->syncGuideTags((int)$row['id'], $tagText);
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

        $row['publish_time'] = !empty($row['publish_time']) ? date('Y-m-d H:i:s', (int)$row['publish_time']) : '';
        $row['tags'] = implode(',', $this->tagService->getGuideTagNames((int)$row['id']));
        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 删除
     *
     * @param string $ids
     * @return \think\response\Json
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

        Db::startTrans();
        try {
            foreach ($rows as $row) {
                $guideId = (int)$row['id'];
                GuideExtValue::where('guide_id', $guideId)->delete();
                GuideTopicBlockItem::where('guide_id', $guideId)->delete();
                $this->tagService->detachGuide($guideId);
            }
            $count = $this->model->where('id', 'in', $ids)->delete();
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        if (!$count) {
            $this->error(__('No rows were deleted'));
        }
        $this->success();
    }

    /**
     * AJAX: 根据模型获取扩展字段定义（含已存值）
     */
    public function getFields()
    {
        $modelId = (int)$this->request->get('model_id', 0);
        $guideId = (int)$this->request->get('guide_id', 0);

        if ($modelId <= 0) {
            $this->success('', null, []);
        }

        $definitions = $this->getFieldDefinitions($modelId);
        if (empty($definitions)) {
            $this->success('', null, []);
        }

        $valuesByFieldId = [];
        if ($guideId > 0) {
            $fieldIds = array_column($definitions, 'id');
            if (!empty($fieldIds)) {
                $rows = GuideExtValue::where('guide_id', $guideId)
                    ->whereIn('field_id', $fieldIds)
                    ->column('value', 'field_id');
                $valuesByFieldId = is_array($rows) ? $rows : [];
            }
        }

        foreach ($definitions as &$item) {
            $type = trim((string)($item['type'] ?? 'text'));
            $item['options'] = $this->decodeFieldOptions($item['options'] ?? '');
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

    /**
     * 校验基础字段
     *
     * @param array $params
     * @return void
     */
    private function validateGuideParams(array &$params)
    {
        if (empty($params['category_id'])) {
            $this->error('请选择攻略分类');
        }
        if (empty($params['model_id'])) {
            $this->error('请选择攻略模型');
        }
        if (empty(trim((string)($params['title'] ?? '')))) {
            $this->error('请填写标题');
        }

        $params['category_id'] = (int)$params['category_id'];
        $params['model_id'] = (int)$params['model_id'];
        $params['views'] = (int)($params['views'] ?? 0);
        $params['is_top'] = empty($params['is_top']) ? 0 : 1;
        $params['weigh'] = (int)($params['weigh'] ?? 0);
        $params['is_external'] = empty($params['is_external']) ? 0 : 1;
        $params['source_url'] = trim((string)($params['source_url'] ?? ''));
        $params['keywords'] = trim((string)($params['keywords'] ?? ''));
        $params['summary'] = trim((string)($params['summary'] ?? ''));
        $params['title'] = trim((string)$params['title']);

        if ($params['summary'] === '') {
            $params['summary'] = mb_substr(strip_tags((string)($params['content'] ?? '')), 0, 120);
        }

        if ($params['source_url'] !== '') {
            $params['is_external'] = 1;
        }
        if ($params['is_external'] === 1) {
            if ($params['source_url'] === '') {
                $this->error('外链攻略必须填写他站链接');
            }
            if (!preg_match('/^https?:\/\//i', $params['source_url'])) {
                $this->error('他站链接必须以 http:// 或 https:// 开头');
            }
        }

        $status = trim((string)($params['status'] ?? 'draft'));
        $statusList = $this->model->getStatusList();
        if (!isset($statusList[$status])) {
            $status = 'draft';
        }
        $params['status'] = $status;

        $publishTime = $this->parseDatetime($params['publish_time'] ?? '');
        if ($publishTime < 1 && $status === 'published') {
            $publishTime = time();
        }
        $params['publish_time'] = $publishTime > 0 ? $publishTime : null;
        $params['admin_id'] = (int)$this->auth->id;
    }

    /**
     * 获取模型扩展字段定义
     *
     * @param int $modelId
     * @return array
     */
    private function getFieldDefinitions($modelId)
    {
        $rows = GuideField::where('status', 'normal')
            ->where('model_id', (int)$modelId)
            ->order('weigh desc, id asc')
            ->field('id,name,key,type,required,options,placeholder')
            ->select();
        return is_object($rows) ? $rows->toArray() : (array)$rows;
    }

    /**
     * 保存扩展字段
     *
     * @param int $guideId
     * @param int $modelId
     * @param array $extData
     * @return void
     */
    private function saveExtFields($guideId, $modelId, $extData)
    {
        $guideId = (int)$guideId;
        if ($guideId < 1) {
            return;
        }
        if (!is_array($extData)) {
            $extData = [];
        }

        $definitions = $this->getFieldDefinitions($modelId);
        GuideExtValue::where('guide_id', $guideId)->delete();
        if (empty($definitions)) {
            return;
        }

        $rows = [];
        foreach ($definitions as $def) {
            $fieldId = (int)($def['id'] ?? 0);
            $fieldKey = trim((string)($def['key'] ?? ''));
            if ($fieldId < 1 || $fieldKey === '') {
                continue;
            }

            $raw = $extData[$fieldKey] ?? null;
            $normalized = $this->normalizeFieldValue($def, $raw);
            $required = (int)($def['required'] ?? 0) === 1;
            if ($required && $normalized['value'] === '') {
                $name = trim((string)($def['name'] ?? '扩展字段'));
                $this->error("请填写{$name}");
            }
            if ($normalized['value'] === '') {
                continue;
            }

            $rows[] = [
                'guide_id'      => $guideId,
                'field_id'      => $fieldId,
                'key'           => $fieldKey,
                'value'         => $normalized['value'],
                'value_number'  => $normalized['value_number'],
                'value_date'    => $normalized['value_date'],
            ];
        }

        if (!empty($rows)) {
            (new GuideExtValue)->insertAll($rows);
        }
    }

    /**
     * 归一化扩展字段值
     *
     * @param array $definition
     * @param mixed $rawValue
     * @return array
     */
    private function normalizeFieldValue(array $definition, $rawValue)
    {
        $type = trim((string)($definition['type'] ?? 'text'));
        $valueNumber = null;
        $valueDate = null;

        if ($type === 'checkbox') {
            $list = is_array($rawValue) ? $rawValue : [];
            $filtered = [];
            foreach ($list as $item) {
                $text = trim((string)$item);
                if ($text !== '') {
                    $filtered[] = $text;
                }
            }
            $value = implode(',', array_values(array_unique($filtered)));
            return ['value' => $value, 'value_number' => null, 'value_date' => null];
        }

        if (is_array($rawValue)) {
            $rawValue = reset($rawValue);
        }
        $value = is_scalar($rawValue) ? trim((string)$rawValue) : '';
        if ($value === '') {
            return ['value' => '', 'value_number' => null, 'value_date' => null];
        }

        if ($type === 'number' && is_numeric($value)) {
            $valueNumber = (float)$value;
        } elseif ($type === 'date') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $valueDate = $value;
            } else {
                $timestamp = strtotime($value);
                if ($timestamp) {
                    $valueDate = date('Y-m-d', $timestamp);
                }
            }
        }

        return ['value' => $value, 'value_number' => $valueNumber, 'value_date' => $valueDate];
    }

    /**
     * 解析字段 options
     *
     * @param mixed $options
     * @return array
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

    /**
     * 解析日期时间
     *
     * @param mixed $value
     * @return int
     */
    private function parseDatetime($value)
    {
        if (empty($value)) {
            return 0;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        $timestamp = strtotime((string)$value);
        return $timestamp ? (int)$timestamp : 0;
    }
}

