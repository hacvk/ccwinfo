<?php

namespace app\common\library;

use app\common\model\GuideTag;
use app\common\model\GuideTagRel;

/**
 * 攻略标签归一化与关系同步服务
 */
class GuideTagService
{
    /**
     * 同步攻略标签关系并更新 use_count
     *
     * @param int $guideId
     * @param string|array $rawTagText
     * @return array
     */
    public function syncGuideTags($guideId, $rawTagText)
    {
        $guideId = (int)$guideId;
        if ($guideId < 1) {
            return [];
        }

        $names = $this->normalizeNames($rawTagText);

        $existsTagIds = GuideTagRel::where('guide_id', $guideId)->column('tag_id');
        $existsTagIds = array_values(array_unique(array_map('intval', (array)$existsTagIds)));

        $desiredTagIds = [];
        $desiredNames = [];
        foreach ($names as $name) {
            $tag = GuideTag::where('name', $name)->find();
            if (!$tag) {
                $tag = new GuideTag;
                $tag->name = $name;
                $tag->slug = $this->buildUniqueSlug($name);
                $tag->use_count = 0;
                $tag->status = 'normal';
                $tag->save();
            }
            $desiredTagIds[] = (int)$tag->id;
            $desiredNames[] = (string)$tag->name;
        }
        $desiredTagIds = array_values(array_unique($desiredTagIds));

        $toAdd = array_values(array_diff($desiredTagIds, $existsTagIds));
        $toRemove = array_values(array_diff($existsTagIds, $desiredTagIds));

        if (!empty($toAdd)) {
            $now = time();
            $rows = [];
            foreach ($toAdd as $tagId) {
                $rows[] = [
                    'guide_id'    => $guideId,
                    'tag_id'      => (int)$tagId,
                    'createtime'  => $now,
                ];
            }
            if (!empty($rows)) {
                (new GuideTagRel)->insertAll($rows);
            }
        }

        if (!empty($toRemove)) {
            GuideTagRel::where('guide_id', $guideId)->whereIn('tag_id', $toRemove)->delete();
        }

        $this->refreshUseCount(array_merge($existsTagIds, $desiredTagIds));
        return $desiredNames;
    }

    /**
     * 清空某攻略的标签关系并回写 use_count
     *
     * @param int $guideId
     * @return void
     */
    public function detachGuide($guideId)
    {
        $guideId = (int)$guideId;
        if ($guideId < 1) {
            return;
        }
        $tagIds = GuideTagRel::where('guide_id', $guideId)->column('tag_id');
        GuideTagRel::where('guide_id', $guideId)->delete();
        $this->refreshUseCount($tagIds);
    }

    /**
     * 获取攻略标签（用于详情页）
     *
     * @param int $guideId
     * @return array
     */
    public function getGuideTags($guideId)
    {
        $guideId = (int)$guideId;
        if ($guideId < 1) {
            return [];
        }
        $rows = GuideTag::alias('t')
            ->join('__GUIDE_TAG_REL__ r', 'r.tag_id = t.id')
            ->where('r.guide_id', $guideId)
            ->field('t.id,t.name,t.slug,t.use_count')
            ->order('t.use_count desc, t.id asc')
            ->select();

        return is_object($rows) ? $rows->toArray() : (array)$rows;
    }

    /**
     * 获取攻略标签名列表（用于后台编辑回填）
     *
     * @param int $guideId
     * @return array
     */
    public function getGuideTagNames($guideId)
    {
        $rows = $this->getGuideTags($guideId);
        $names = [];
        foreach ($rows as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    }

    /**
     * 归一化标签输入
     *
     * @param string|array $rawTagText
     * @return array
     */
    public function normalizeNames($rawTagText)
    {
        if (is_array($rawTagText)) {
            $rawTagText = implode(',', $rawTagText);
        }
        $text = (string)$rawTagText;
        $text = str_replace(["\r\n", "\r", "\n", '，', '、', ';', '；', '|'], ',', $text);
        $parts = array_filter(array_map('trim', explode(',', $text)), function ($v) {
            return $v !== '';
        });

        $result = [];
        $seen = [];
        foreach ($parts as $name) {
            $name = preg_replace('/\s+/u', ' ', trim($name));
            if ($name === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $name;
        }
        return $result;
    }

    /**
     * 批量刷新标签 use_count
     *
     * @param array $tagIds
     * @return void
     */
    public function refreshUseCount($tagIds)
    {
        $tagIds = array_values(array_unique(array_filter(array_map('intval', (array)$tagIds))));
        if (empty($tagIds)) {
            return;
        }
        foreach ($tagIds as $tagId) {
            $count = (int)GuideTagRel::where('tag_id', $tagId)->count();
            GuideTag::where('id', $tagId)->update(['use_count' => $count]);
        }
    }

    /**
     * 生成唯一 slug
     *
     * @param string $name
     * @return string
     */
    private function buildUniqueSlug($name)
    {
        $base = $this->slugify($name);
        if ($base === '') {
            $base = 'tag';
        }
        $slug = $base;
        $suffix = 2;
        while (GuideTag::where('slug', $slug)->find()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    /**
     * slug 归一化
     *
     * @param string $text
     * @return string
     */
    private function slugify($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }
        $slug = strtolower((string)get_pinyin_abbrev($text, 20));
        if ($slug === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
        }
        $slug = trim((string)$slug, '-');
        return preg_replace('/-+/', '-', $slug);
    }
}

