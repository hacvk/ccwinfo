<?php

namespace app\admin\controller\promotion;

use app\common\controller\Backend;
use app\common\library\SentryService;
use app\common\model\PromotionItem as PromotionItemModel;
use app\common\model\PromotionOrder;
use app\common\model\PromotionSlot;
use app\common\model\InfoCategory;
use think\Db;

/**
 * 推广内容管理
 *
 * @icon fa fa-bullhorn
 */
class Item extends Backend
{
    /**
     * @var \app\common\model\PromotionItem
     */
    protected $model = null;

    protected $relationSearch = true;
    protected $searchFields = 'id,title,link_url,contact_name,contact_mobile,company_name';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new PromotionItemModel;

        // 展示位下拉
        $slotList = PromotionSlot::where('status', 'normal')->order('id asc')->column('name', 'id');
        // 一级分类下拉
        $categoryList = InfoCategory::where('pid', 0)->where('status', 'normal')->order('weigh desc, id asc')->column('name', 'id');

        $this->view->assign('slotList', $slotList);
        $this->view->assign('categoryList', $categoryList);
        $this->view->assign('statusList', $this->model->getStatusList());
        $this->view->assign('reviewStatusList', $this->model->getReviewStatusList());
        $this->assignconfig('statusList', $this->model->getStatusList());
        $this->assignconfig('reviewStatusList', $this->model->getReviewStatusList());
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
            ->with(['slot'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        foreach ($list as $row) {
            $row->getRelation('slot');
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
            $this->normalizeTimeFields($params);

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
            $this->normalizeTimeFields($params);

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

        // 将时间字段格式化为日期时间字符串供前端展示
        $row['start_time_text'] = $row['start_time'] ? date('Y-m-d H:i:s', (int)$row['start_time']) : '';
        $row['end_time_text'] = $row['end_time'] ? date('Y-m-d H:i:s', (int)$row['end_time']) : '';

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 审核
     */
    public function review($ids = null)
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

            $reviewStatus = $params['review_status'] ?? '';
            if (!in_array($reviewStatus, ['approved', 'rejected'])) {
                $this->error('无效的审核状态');
            }

            $adminId = session('admin.id');
            $slot = PromotionSlot::get((int)$row['slot_id']);
            $isFriendLink = $slot && (string)$slot['code'] === 'friend_links';
            $order = PromotionOrder::where('item_id', (int)$row['id'])->order('id desc')->find();

            if (!$isFriendLink && $reviewStatus === 'approved') {
                if (!$order) {
                    $this->error('该推广内容缺少订单，无法通过审核');
                }
                if (!in_array((string)$order['pay_status'], ['paid', 'offline_paid'])) {
                    $this->error('该推广内容尚未支付，不能通过审核');
                }
            }
            
            Db::startTrans();
            try {
                $now = time();
                $row->review_status = $reviewStatus;
                $row->review_time = $now;
                $row->review_admin_id = $adminId;
                $row->review_remark = $params['review_remark'] ?? '';

                if ($reviewStatus === 'approved') {
                    $row->status = 'online';
                    $row->start_time = $now;
                    $durationDays = (int)$row['duration_days'];
                    if ($durationDays < 1) {
                        $cycle = (string)$row['billing_cycle'];
                        $durationDays = $cycle === 'quarter' ? 90 : ($cycle === 'month' ? 30 : 7);
                        $row->duration_days = $durationDays;
                    }
                    $row->end_time = $now + ($durationDays * 86400);
                } else {
                    $row->status = 'offline';
                }

                $row->save();

                if ($order) {
                    $orderStatus = $reviewStatus === 'approved' ? 'approved' : 'rejected';
                    $order->order_status = $orderStatus;
                    $order->save();
                }
                Db::commit();

                SentryService::info('Item.review 完成', [
                    'item_id' => $row['id'],
                    'title' => $row['title'],
                    'review_status' => $reviewStatus,
                    'admin_id' => $adminId,
                ]);
            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            $this->success();
        }

        $this->view->assign('row', $row);
        return $this->view->fetch();
    }

    /**
     * 归一化时间字段（字符串 -> 时间戳）
     *
     * @param array $params
     */
    protected function normalizeTimeFields(array &$params)
    {
        foreach (['start_time', 'end_time'] as $field) {
            if (!isset($params[$field]) || $params[$field] === '') {
                $params[$field] = null;
                continue;
            }
            $value = $params[$field];
            if (is_numeric($value)) {
                $params[$field] = (int)$value ?: null;
            } else {
                $timestamp = strtotime((string)$value);
                $params[$field] = $timestamp ?: null;
            }
        }
    }
}
