<?php

namespace app\common\library;

use app\common\model\Guide;
use app\common\model\GuideTagRel;
use app\common\model\GuideTopic;
use app\common\model\GuideTopicBlock;
use app\common\model\GuideTopicBlockItem;

/**
 * 专题聚合渲染服务
 */
class GuideTopicService
{
    /**
     * 当前时间戳
     *
     * @var int
     */
    protected $now = 0;

    public function __construct()
    {
        $this->now = time();
    }

    /**
     * 获取前台可见专题
     *
     * @param string $slug
     * @return GuideTopic|null
     */
    public function getPublishedTopicBySlug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return null;
        }
        return GuideTopic::where('slug', $slug)
            ->where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->find();
    }

    /**
     * 渲染专题模块
     *
     * @param int $topicId
     * @return array
     */
    public function buildBlocks($topicId)
    {
        $topicId = (int)$topicId;
        if ($topicId < 1) {
            return [];
        }

        $blocks = GuideTopicBlock::where('topic_id', $topicId)
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $blocks = is_object($blocks) ? $blocks->toArray() : (array)$blocks;
        if (empty($blocks)) {
            return [];
        }

        $result = [];
        $renderedGuideIds = [];
        foreach ($blocks as $block) {
            $type = trim((string)($block['type'] ?? ''));
            $items = [];

            if ($type === 'manual_list') {
                $items = $this->buildManualListItems((int)$block['id']);
                foreach ($items as $item) {
                    $renderedGuideIds[] = (int)($item['id'] ?? 0);
                }
            } elseif ($type === 'rule_list') {
                // 同一专题内规则区块默认去重已渲染攻略
                $items = $this->buildRuleListItems((string)($block['rule_json'] ?? ''), $renderedGuideIds);
                foreach ($items as $item) {
                    $renderedGuideIds[] = (int)($item['id'] ?? 0);
                }
            } elseif ($type === 'external_cards') {
                $items = $this->buildExternalCardItems((int)$block['id']);
            }

            $renderedGuideIds = array_values(array_unique(array_filter(array_map('intval', $renderedGuideIds))));

            if (empty($items)) {
                continue;
            }

            $result[] = [
                'id'    => (int)$block['id'],
                'type'  => $type,
                'title' => trim((string)($block['title'] ?? '')),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * 手选攻略区块
     *
     * @param int $blockId
     * @return array
     */
    protected function buildManualListItems($blockId)
    {
        $rows = GuideTopicBlockItem::where('block_id', (int)$blockId)
            ->where('item_type', 'guide')
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        if (empty($rows)) {
            return [];
        }

        $guideIds = array_values(array_unique(array_filter(array_map(function ($item) {
            return (int)($item['guide_id'] ?? 0);
        }, $rows))));
        if (empty($guideIds)) {
            return [];
        }

        $guideRows = Guide::where('id', 'in', $guideIds)
            ->where('status', 'published')
            ->where('publish_time', '<=', $this->now)
            ->select();
        $guideRows = is_object($guideRows) ? $guideRows->toArray() : (array)$guideRows;
        if (empty($guideRows)) {
            return [];
        }

        $guideMap = [];
        foreach ($this->normalizeGuideRows($guideRows) as $guide) {
            $guideMap[(int)$guide['id']] = $guide;
        }

        $result = [];
        foreach ($rows as $row) {
            $guideId = (int)($row['guide_id'] ?? 0);
            if ($guideId < 1 || !isset($guideMap[$guideId])) {
                continue;
            }
            $guide = $guideMap[$guideId];

            // 手选条目允许覆盖标题/图片/摘要
            $manualTitle = trim((string)($row['title'] ?? ''));
            $manualImage = trim((string)($row['image'] ?? ''));
            $manualSummary = trim((string)($row['summary'] ?? ''));
            if ($manualTitle !== '') {
                $guide['title'] = $manualTitle;
            }
            if ($manualImage !== '') {
                $guide['cover'] = $manualImage;
            }
            if ($manualSummary !== '') {
                $guide['summary'] = $manualSummary;
            }

            $result[] = $guide;
        }
        return $result;
    }

    /**
     * 规则聚合区块
     *
     * @param string $ruleJson
     * @param array $excludeGuideIds
     * @return array
     */
    protected function buildRuleListItems($ruleJson, array $excludeGuideIds = [])
    {
        $rule = $this->parseRule($ruleJson);
        $query = Guide::where('status', 'published')
            ->where('publish_time', '<=', $this->now);

        if (!$rule['include_external']) {
            $query->where('is_external', 0);
        }
        if (!empty($rule['category_ids'])) {
            $query->where('category_id', 'in', $rule['category_ids']);
        }
        if (!empty($rule['model_ids'])) {
            $query->where('model_id', 'in', $rule['model_ids']);
        }
        if (!empty($excludeGuideIds)) {
            $query->where('id', 'not in', array_values(array_unique(array_map('intval', $excludeGuideIds))));
        }

        if (!empty($rule['tag_ids'])) {
            $guideIdsByTag = GuideTagRel::where('tag_id', 'in', $rule['tag_ids'])->column('guide_id');
            $guideIdsByTag = array_values(array_unique(array_filter(array_map('intval', (array)$guideIdsByTag))));
            if (empty($guideIdsByTag)) {
                return [];
            }
            $query->where('id', 'in', $guideIdsByTag);
        }

        $rows = $query->order($rule['order_by'], $rule['order_dir'])
            ->limit($rule['limit'])
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;

        return $this->normalizeGuideRows($rows);
    }

    /**
     * 外链卡片区块
     *
     * @param int $blockId
     * @return array
     */
    protected function buildExternalCardItems($blockId)
    {
        $rows = GuideTopicBlockItem::where('block_id', (int)$blockId)
            ->where('item_type', 'external')
            ->where('status', 'normal')
            ->order('weigh desc, id asc')
            ->field('id,title,url,image,summary')
            ->select();
        $rows = is_object($rows) ? $rows->toArray() : (array)$rows;
        if (empty($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $url = trim((string)($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $result[] = [
                'id'          => 0,
                'title'       => trim((string)($row['title'] ?? '')),
                'summary'     => trim((string)($row['summary'] ?? '')),
                'cover'       => trim((string)($row['image'] ?? '')),
                'jump_url'    => $url,
                'detail_url'  => $url,
                'is_external' => 1,
                'attachments' => [],
                'tags'        => [],
            ];
        }
        return $result;
    }

    /**
     * 解析 rule_json
     *
     * @param string $ruleJson
     * @return array
     */
    protected function parseRule($ruleJson)
    {
        $default = [
            'category_ids'     => [],
            'model_ids'        => [],
            'tag_ids'          => [],
            'order_by'         => 'publish_time',
            'order_dir'        => 'desc',
            'limit'            => 10,
            'include_external' => 1,
        ];

        $data = json_decode((string)$ruleJson, true);
        if (!is_array($data)) {
            return $default;
        }

        $data['category_ids'] = $this->normalizeIdList($data['category_ids'] ?? []);
        $data['model_ids'] = $this->normalizeIdList($data['model_ids'] ?? []);
        $data['tag_ids'] = $this->normalizeIdList($data['tag_ids'] ?? []);

        $allowOrderBy = ['id', 'publish_time', 'views', 'weigh', 'createtime'];
        $orderBy = trim((string)($data['order_by'] ?? ''));
        if (!in_array($orderBy, $allowOrderBy, true)) {
            $orderBy = $default['order_by'];
        }
        $orderDir = strtolower(trim((string)($data['order_dir'] ?? 'desc')));
        if (!in_array($orderDir, ['asc', 'desc'], true)) {
            $orderDir = 'desc';
        }

        $limit = (int)($data['limit'] ?? 10);
        if ($limit < 1) {
            $limit = 10;
        } elseif ($limit > 100) {
            $limit = 100;
        }

        $includeExternal = !empty($data['include_external']) ? 1 : 0;

        return [
            'category_ids'     => $data['category_ids'],
            'model_ids'        => $data['model_ids'],
            'tag_ids'          => $data['tag_ids'],
            'order_by'         => $orderBy,
            'order_dir'        => $orderDir,
            'limit'            => $limit,
            'include_external' => $includeExternal,
        ];
    }

    /**
     * 归一化攻略结果字段
     *
     * @param array $rows
     * @return array
     */
    protected function normalizeGuideRows(array $rows)
    {
        if (empty($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $images = $this->parseMultiField($row['images'] ?? '');
            $attachments = $this->parseMultiField($row['attachments'] ?? '');
            $cover = trim((string)($row['cover'] ?? ''));
            if ($cover === '' && !empty($images[0])) {
                $cover = $images[0];
            }

            $isExternal = (int)($row['is_external'] ?? 0) === 1 && trim((string)($row['source_url'] ?? '')) !== '';
            $detailUrl = guide_url($row);
            $jumpUrl = $isExternal ? trim((string)$row['source_url']) : $detailUrl;

            $result[] = [
                'id'          => $id,
                'title'       => trim((string)($row['title'] ?? '')),
                'summary'     => trim((string)($row['summary'] ?? '')),
                'content'     => (string)($row['content'] ?? ''),
                'cover'       => $cover,
                'images'      => $images,
                'attachments' => $attachments,
                'views'       => (int)($row['views'] ?? 0),
                'publish_time'=> (int)($row['publish_time'] ?? 0),
                'detail_url'  => $detailUrl,
                'jump_url'    => $jumpUrl,
                'is_external' => $isExternal ? 1 : 0,
                'tags'        => [],
            ];
        }
        return $result;
    }

    /**
     * 归一化 ID 列表
     *
     * @param mixed $value
     * @return array
     */
    protected function normalizeIdList($value)
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
     * 解析多值字段（JSON/逗号）
     *
     * @param mixed $value
     * @return array
     */
    protected function parseMultiField($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), function ($v) {
                return $v !== '';
            }));
        }
        $text = trim((string)$value);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), function ($v) {
                return $v !== '';
            }));
        }
        return array_values(array_filter(array_map('trim', explode(',', $text)), function ($v) {
            return $v !== '';
        }));
    }
}

