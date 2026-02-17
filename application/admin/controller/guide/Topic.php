<?php

namespace app\admin\controller\guide;

use app\common\controller\Backend;
use app\common\model\GuideTopic;
use app\common\model\GuideTopicBlock;
use app\common\model\GuideTopicBlockItem;
use think\Db;

/**
 * 本地攻略专题管理
 *
 * @icon fa fa-th-large
 */
class Topic extends Backend
{
    /**
     * @var \app\common\model\GuideTopic
     */
    protected $model = null;
    protected $searchFields = 'id,title,slug,keywords';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new GuideTopic;
        $blockModel = new GuideTopicBlock;
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->view->assign('blockTypeList', $blockModel->getTypeList());
        $this->assignconfig('statusList', $this->model->getStatusList());
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
            $blocksPayload = (string)$this->request->post('blocks_payload', '[]');
            $blocks = $this->parseBlocksPayload($blocksPayload);
            $this->validateTopicParams($params);

            $result = false;
            Db::startTrans();
            try {
                $result = $this->model->allowField(true)->save($params);
                if ($result !== false) {
                    $this->syncBlocks((int)$this->model->id, $blocks);
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
            'publish_time' => date('Y-m-d H:i:s'),
            'weigh'        => 0,
        ]);
        $this->view->assign('blocksPayload', "[]");
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
            $blocksPayload = (string)$this->request->post('blocks_payload', '[]');
            $blocks = $this->parseBlocksPayload($blocksPayload);
            $this->validateTopicParams($params, (int)$row['id']);

            $result = false;
            Db::startTrans();
            try {
                $result = $row->allowField(true)->save($params);
                $this->syncBlocks((int)$row['id'], $blocks);
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
        $this->view->assign('row', $row);
        $this->view->assign('blocksPayload', $this->buildBlocksPayload((int)$row['id']));
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

        Db::startTrans();
        try {
            foreach ($ids as $topicId) {
                $blockIds = GuideTopicBlock::where('topic_id', $topicId)->column('id');
                if (!empty($blockIds)) {
                    GuideTopicBlockItem::where('block_id', 'in', $blockIds)->delete();
                }
                GuideTopicBlock::where('topic_id', $topicId)->delete();
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
     * 校验专题参数
     *
     * @param array $params
     * @param int $excludeId
     * @return void
     */
    private function validateTopicParams(array &$params, $excludeId = 0)
    {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            $this->error('请填写专题标题');
        }

        $params['title'] = $title;
        $params['summary'] = trim((string)($params['summary'] ?? ''));
        $params['keywords'] = trim((string)($params['keywords'] ?? ''));
        $params['description'] = trim((string)($params['description'] ?? ''));
        $params['weigh'] = (int)($params['weigh'] ?? 0);

        $status = trim((string)($params['status'] ?? 'draft'));
        $statusList = $this->model->getStatusList();
        if (!isset($statusList[$status])) {
            $status = 'draft';
        }
        $params['status'] = $status;

        $slug = trim((string)($params['slug'] ?? ''));
        if ($slug === '') {
            $slug = strtolower((string)get_pinyin_abbrev($title, 32));
        }
        if ($slug === '') {
            $slug = 'topic';
        }
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($slug));
        $slug = trim((string)$slug, '-');
        if ($slug === '') {
            $slug = 'topic';
        }
        $params['slug'] = $this->makeUniqueSlug($slug, $excludeId);

        $publishTime = $this->parseDatetime($params['publish_time'] ?? '');
        if ($publishTime < 1 && $status === 'published') {
            $publishTime = time();
        }
        $params['publish_time'] = $publishTime > 0 ? $publishTime : null;
    }

    /**
     * 生成唯一 slug
     *
     * @param string $base
     * @param int $excludeId
     * @return string
     */
    private function makeUniqueSlug($base, $excludeId = 0)
    {
        $slug = $base;
        $suffix = 2;
        while (true) {
            $query = GuideTopic::where('slug', $slug);
            if ($excludeId > 0) {
                $query->where('id', '<>', (int)$excludeId);
            }
            if (!$query->find()) {
                break;
            }
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    /**
     * 解析并校验专题区块 JSON
     *
     * @param string $payload
     * @return array
     */
    private function parseBlocksPayload($payload)
    {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            $this->error('专题模块 JSON 格式错误');
        }

        $allowType = ['manual_list', 'rule_list', 'external_cards'];
        $result = [];
        foreach ($decoded as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = trim((string)($block['type'] ?? ''));
            if (!in_array($type, $allowType, true)) {
                continue;
            }
            $row = [
                'type'      => $type,
                'title'     => trim((string)($block['title'] ?? '')),
                'weigh'     => (int)($block['weigh'] ?? 0),
                'status'    => trim((string)($block['status'] ?? 'normal')) === 'hidden' ? 'hidden' : 'normal',
                'rule_json' => '',
                'items'     => [],
            ];

            if ($type === 'rule_list') {
                $row['rule_json'] = $this->normalizeRuleJson($block['rule_json'] ?? []);
            } elseif ($type === 'manual_list') {
                $row['items'] = $this->normalizeManualItems($block['items'] ?? []);
            } elseif ($type === 'external_cards') {
                $row['items'] = $this->normalizeExternalItems($block['items'] ?? []);
            }

            $result[] = $row;
        }
        return $result;
    }

    /**
     * 同步专题区块与条目
     *
     * @param int $topicId
     * @param array $blocks
     * @return void
     */
    private function syncBlocks($topicId, array $blocks)
    {
        $topicId = (int)$topicId;
        $oldBlockIds = GuideTopicBlock::where('topic_id', $topicId)->column('id');
        if (!empty($oldBlockIds)) {
            GuideTopicBlockItem::where('block_id', 'in', $oldBlockIds)->delete();
        }
        GuideTopicBlock::where('topic_id', $topicId)->delete();

        foreach ($blocks as $block) {
            $blockModel = new GuideTopicBlock;
            $blockModel->save([
                'topic_id'   => $topicId,
                'type'       => $block['type'],
                'title'      => $block['title'],
                'rule_json'  => $block['rule_json'],
                'weigh'      => (int)$block['weigh'],
                'status'     => $block['status'],
            ]);
            $blockId = (int)$blockModel->id;

            if (empty($block['items'])) {
                continue;
            }
            $itemRows = [];
            foreach ($block['items'] as $item) {
                $itemRows[] = [
                    'block_id'   => $blockId,
                    'item_type'  => $item['item_type'],
                    'guide_id'   => (int)$item['guide_id'],
                    'title'      => $item['title'],
                    'url'        => $item['url'],
                    'image'      => $item['image'],
                    'summary'    => $item['summary'],
                    'weigh'      => (int)$item['weigh'],
                    'status'     => $item['status'],
                    'createtime' => time(),
                    'updatetime' => time(),
                ];
            }
            if (!empty($itemRows)) {
                (new GuideTopicBlockItem)->insertAll($itemRows);
            }
        }
    }

    /**
     * 生成编辑页回填 JSON
     *
     * @param int $topicId
     * @return string
     */
    private function buildBlocksPayload($topicId)
    {
        $topicId = (int)$topicId;
        if ($topicId < 1) {
            return "[]";
        }

        $blocks = GuideTopicBlock::where('topic_id', $topicId)
            ->order('weigh desc, id asc')
            ->select();
        $blocks = is_object($blocks) ? $blocks->toArray() : (array)$blocks;
        if (empty($blocks)) {
            return "[]";
        }

        $result = [];
        foreach ($blocks as $block) {
            $blockId = (int)$block['id'];
            $row = [
                'type'      => (string)$block['type'],
                'title'     => (string)($block['title'] ?? ''),
                'weigh'     => (int)($block['weigh'] ?? 0),
                'status'    => (string)($block['status'] ?? 'normal'),
                'rule_json' => (string)($block['rule_json'] ?? ''),
                'items'     => [],
            ];

            $items = GuideTopicBlockItem::where('block_id', $blockId)
                ->order('weigh desc, id asc')
                ->select();
            $items = is_object($items) ? $items->toArray() : (array)$items;
            foreach ($items as $item) {
                $row['items'][] = [
                    'item_type' => (string)($item['item_type'] ?? ''),
                    'guide_id'  => (int)($item['guide_id'] ?? 0),
                    'title'     => (string)($item['title'] ?? ''),
                    'url'       => (string)($item['url'] ?? ''),
                    'image'     => (string)($item['image'] ?? ''),
                    'summary'   => (string)($item['summary'] ?? ''),
                    'weigh'     => (int)($item['weigh'] ?? 0),
                    'status'    => (string)($item['status'] ?? 'normal'),
                ];
            }
            $result[] = $row;
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * 归一化 manual_list 条目
     *
     * @param mixed $items
     * @return array
     */
    private function normalizeManualItems($items)
    {
        $items = is_array($items) ? $items : [];
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $guideId = (int)($item['guide_id'] ?? 0);
            if ($guideId < 1) {
                continue;
            }
            $result[] = [
                'item_type' => 'guide',
                'guide_id'  => $guideId,
                'title'     => trim((string)($item['title'] ?? '')),
                'url'       => '',
                'image'     => trim((string)($item['image'] ?? '')),
                'summary'   => trim((string)($item['summary'] ?? '')),
                'weigh'     => (int)($item['weigh'] ?? 0),
                'status'    => trim((string)($item['status'] ?? 'normal')) === 'hidden' ? 'hidden' : 'normal',
            ];
        }
        return $result;
    }

    /**
     * 归一化 external_cards 条目
     *
     * @param mixed $items
     * @return array
     */
    private function normalizeExternalItems($items)
    {
        $items = is_array($items) ? $items : [];
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $result[] = [
                'item_type' => 'external',
                'guide_id'  => 0,
                'title'     => trim((string)($item['title'] ?? '')),
                'url'       => $url,
                'image'     => trim((string)($item['image'] ?? '')),
                'summary'   => trim((string)($item['summary'] ?? '')),
                'weigh'     => (int)($item['weigh'] ?? 0),
                'status'    => trim((string)($item['status'] ?? 'normal')) === 'hidden' ? 'hidden' : 'normal',
            ];
        }
        return $result;
    }

    /**
     * 归一化 rule_json
     *
     * @param mixed $rule
     * @return string
     */
    private function normalizeRuleJson($rule)
    {
        if (is_string($rule)) {
            $decoded = json_decode($rule, true);
            $rule = is_array($decoded) ? $decoded : [];
        }
        $rule = is_array($rule) ? $rule : [];

        $allowOrderBy = ['id', 'publish_time', 'views', 'weigh', 'createtime'];
        $orderBy = trim((string)($rule['order_by'] ?? 'publish_time'));
        if (!in_array($orderBy, $allowOrderBy, true)) {
            $orderBy = 'publish_time';
        }
        $orderDir = strtolower(trim((string)($rule['order_dir'] ?? 'desc')));
        if (!in_array($orderDir, ['asc', 'desc'], true)) {
            $orderDir = 'desc';
        }

        $normalized = [
            'category_ids'     => $this->normalizeIdList($rule['category_ids'] ?? []),
            'model_ids'        => $this->normalizeIdList($rule['model_ids'] ?? []),
            'tag_ids'          => $this->normalizeIdList($rule['tag_ids'] ?? []),
            'order_by'         => $orderBy,
            'order_dir'        => $orderDir,
            'limit'            => max(1, min(100, (int)($rule['limit'] ?? 10))),
            'include_external' => !empty($rule['include_external']) ? 1 : 0,
        ];
        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 归一化 ID 列表
     *
     * @param mixed $value
     * @return array
     */
    private function normalizeIdList($value)
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        $value = is_array($value) ? $value : [];
        return array_values(array_unique(array_filter(array_map('intval', $value), function ($v) {
            return $v > 0;
        })));
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

