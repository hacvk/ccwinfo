<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\InfoCategory;
use app\common\model\PromotionItem;
use app\common\model\PromotionOrder;
use app\common\model\PromotionSlot;
use think\Db;

/**
 * 前台推广与友情链接申请
 */
class Promotion extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['apply', 'submit', 'success', 'status'];
    protected $noNeedRight = ['*'];

    /**
     * 申请页面
     */
    public function apply()
    {
        $slotCode = trim((string)$this->request->param('slot_code', 'friend_links'));
        if ($slotCode === '') {
            $slotCode = 'friend_links';
        }

        $slot = PromotionSlot::where('code', $slotCode)
            ->where('status', 'normal')
            ->find();
        if (!$slot) {
            $this->error('所选推广位置不可用');
        }

        // 一级分类列表（用于按分类投放的推广位）
        $categoryList = InfoCategory::getCategoryList(0);

        $this->assign('slot', $slot);
        $this->assign('slotCode', $slotCode);
        $this->assign('categoryList', $categoryList);
        $this->assign('title', '申请推广位');

        return $this->view->fetch('promotion/apply');
    }

    /**
     * 提交申请
     */
    public function submit()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $slotId = (int)$this->request->post('slot_id', 0);
        $slotCode = trim((string)$this->request->post('slot_code', ''));

        if ($slotId > 0) {
            $slot = PromotionSlot::get($slotId);
        } else {
            $slot = null;
            if ($slotCode !== '') {
                $slot = PromotionSlot::where('code', $slotCode)->find();
            }
        }

        if (!$slot || (string)$slot['status'] !== 'normal') {
            $this->error('所选推广位置不可用');
        }

        $title = trim((string)$this->request->post('title', ''));
        $linkUrl = trim((string)$this->request->post('link_url', ''));
        $image = trim((string)$this->request->post('image', ''));
        $categoryId = (int)$this->request->post('category_id', 0);
        $pageScope = trim((string)$this->request->post('page_scope', 'all'));
        $contactName = trim((string)$this->request->post('contact_name', ''));
        $contactMobile = trim((string)$this->request->post('contact_mobile', ''));
        $companyName = trim((string)$this->request->post('company_name', ''));
        $remark = trim((string)$this->request->post('remark', ''));
        $amount = (float)$this->request->post('amount', 0);

        if ($title === '') {
            $this->error('请填写标题/链接文字');
        }
        if ($contactMobile === '') {
            $this->error('请填写联系电话');
        }

        $user = $this->auth ? $this->auth->getUser() : null;
        $userId = $user ? (int)$user->id : 0;

        Db::startTrans();
        try {
            $item = new PromotionItem();
            $item->slot_id = (int)$slot['id'];
            $item->title = $title;
            $item->link_url = $linkUrl;
            $item->image = $image;
            $item->category_id = $categoryId;
            $item->page_scope = $pageScope !== '' ? $pageScope : 'all';
            $item->sort = 0;
            $item->status = 'draft';
            $item->review_status = 'pending';
            $item->created_by_user_id = $userId;
            $item->contact_name = $contactName;
            $item->contact_mobile = $contactMobile;
            $item->company_name = $companyName;
            $item->save();

            $order = new PromotionOrder();
            $order->item_id = (int)$item->id;
            $order->user_id = $userId;
            $order->slot_id = (int)$slot['id'];
            $order->category_id = $categoryId;
            $order->amount = $amount > 0 ? $amount : 0;
            $order->pay_status = 'unpaid';
            $order->order_status = 'pending';
            $order->contact_name = $contactName;
            $order->contact_mobile = $contactMobile;
            $order->company_name = $companyName;
            $order->remark = $remark;
            $order->offline_voucher = '';
            $order->save();

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('提交失败：' . $e->getMessage());
        }

        $this->success('提交成功，我们会尽快与您联系并审核', url('promotion/applySuccess', ['id' => (int)$order->id]));
    }

    /**
     * 提交成功页
     */
    public function applySuccess()
    {
        $id = (int)$this->request->param('id', 0);
        if ($id < 1) {
            $this->error('订单不存在');
        }
        $order = PromotionOrder::get($id);
        if (!$order) {
            $this->error('订单不存在');
        }

        $this->assign('order', $order);
        $this->assign('title', '申请已提交');
        return $this->view->fetch('promotion/success');
    }

    /**
     * 订单状态查询
     */
    public function status()
    {
        $id = (int)$this->request->param('id', 0);
        $mobile = trim((string)$this->request->param('mobile', ''));

        if ($id < 1) {
            $this->error('请提供订单ID');
        }

        $query = PromotionOrder::where('id', $id);
        if ($mobile !== '') {
            $query->where('contact_mobile', $mobile);
        }
        $order = $query->find();
        if (!$order) {
            $this->error('未找到对应订单，请确认信息是否正确');
        }

        $this->assign('order', $order);
        $this->assign('title', '订单状态查询');
        return $this->view->fetch('promotion/status');
    }
}
