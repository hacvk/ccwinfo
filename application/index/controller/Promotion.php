<?php

namespace app\index\controller;

use addons\epay\library\Service;
use app\common\controller\Frontend;
use app\common\model\InfoCategory;
use app\common\model\PromotionItem;
use app\common\model\PromotionOrder;
use app\common\model\PromotionSlot;
use think\Config;
use think\Db;
use think\Log;

class Promotion extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['apply', 'friendlinkapply', 'submitfriendlink', 'applysuccess', 'status', 'epay'];
    protected $noNeedRight = ['*'];

    public function apply()
    {
        $slotCode = trim((string)$this->request->param('slot_code', ''));
        if ($slotCode === 'friend_links') {
            return $this->redirect(url('promotion/friendlinkApply'));
        }

        $slotRows = PromotionSlot::where('status', 'normal')
            ->where('code', '<>', 'friend_links')
            ->order('id asc')
            ->select();
        $slotList = is_object($slotRows) ? $slotRows->toArray() : (array)$slotRows;
        if (empty($slotList)) {
            $this->error('暂无可申请的推广位');
        }

        if ($slotCode === '') {
            $slotCode = (string)$slotList[0]['code'];
        }

        $slot = null;
        foreach ($slotList as $slotRow) {
            if ((string)$slotRow['code'] === $slotCode) {
                $slot = $slotRow;
                break;
            }
        }
        if (!$slot) {
            $slot = $slotList[0];
            $slotCode = (string)$slot['code'];
        }

        if (!$this->auth->isLogin()) {
            $target = url('promotion/apply', ['slot_code' => $slotCode], false, true);
            $this->error('请先登录后申请推广', url('user/login', ['url' => $target]));
        }

        $slotModel = PromotionSlot::get((int)$slot['id']);
        if (!$slotModel) {
            $this->error('所选推广位不可用');
        }

        $slotConfig = $this->normalizeSlotConfig($slotModel->getConfigArray());
        $categoryRows = InfoCategory::where('pid', 0)->where('status', 'normal')->order('weigh desc,id asc')->select();
        $categoryRows = is_object($categoryRows) ? $categoryRows->toArray() : (array)$categoryRows;
        $categoryOptions = [];
        foreach ($categoryRows as $categoryRow) {
            $categoryOptions[] = [
                'value' => (string)$categoryRow['id'],
                'label' => (string)$categoryRow['name'],
            ];
        }

        $this->assign('slot', $slotModel);
        $this->assign('slotCode', $slotCode);
        $this->assign('slotList', $slotList);
        $this->assign('slotConfig', $slotConfig);
        $this->assign('applyConfig', [
            'slotCode' => $slotCode,
            'pricing' => $slotConfig['pricing'],
            'formFields' => $slotConfig['form_fields'],
            'categoryList' => $categoryOptions,
            'billingCycles' => [
                ['value' => 'week', 'label' => '按周(7天)'],
                ['value' => 'month', 'label' => '按月(30天)'],
                ['value' => 'quarter', 'label' => '按季度(90天)'],
            ],
        ]);
        $this->assign('page_css', [$this->getCdnUrl() . '/assets/frontend/css/promotion-apply.css']);
        $this->assign('title', '推广位申请');
        return $this->view->fetch('promotion/apply');
    }

    public function friendlinkApply()
    {
        $slot = PromotionSlot::where('code', 'friend_links')->where('status', 'normal')->find();
        if (!$slot) {
            $this->error('友情链接申请入口暂不可用');
        }

        $this->assign('slot', $slot);
        $this->assign('submitted', (int)$this->request->param('submitted', 0));
        $this->assign('page_css', [$this->getCdnUrl() . '/assets/frontend/css/promotion-apply.css']);
        $this->assign('title', '申请友情链接');
        return $this->view->fetch('promotion/friendlink_apply');
    }

    public function submitFriendlink()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $this->token();

        $slot = PromotionSlot::where('code', 'friend_links')->where('status', 'normal')->find();
        if (!$slot) {
            $this->error('友情链接申请入口暂不可用');
        }

        $title = trim((string)$this->request->post('title', ''));
        $linkUrl = trim((string)$this->request->post('link_url', ''));
        $contactName = trim((string)$this->request->post('contact_name', ''));
        $contactMobile = trim((string)$this->request->post('contact_mobile', ''));
        $companyName = trim((string)$this->request->post('company_name', ''));
        $remark = trim((string)$this->request->post('remark', ''));

        if ($title === '') {
            $this->error('请填写网站名称');
        }
        if ($linkUrl !== '' && !$this->isValidUrl($linkUrl)) {
            $this->error('请填写正确的网站链接');
        }
        if ($contactName === '') {
            $this->error('请填写联系人');
        }
        if (!$this->isValidMobile($contactMobile)) {
            $this->error('请填写正确的联系电话');
        }

        $user = $this->auth ? $this->auth->getUser() : null;
        $userId = $user ? (int)$user->id : 0;

        Db::startTrans();
        try {
            $item = new PromotionItem();
            $item->allowField(true)->save([
                'slot_id' => (int)$slot['id'],
                'title' => $title,
                'sub_title' => '',
                'link_url' => $linkUrl,
                'image' => '',
                'category_id' => 0,
                'page_scope' => 'all',
                'sort' => 0,
                'status' => 'draft',
                'review_status' => 'pending',
                'created_by_user_id' => $userId,
                'contact_name' => $contactName,
                'contact_mobile' => $contactMobile,
                'company_name' => $companyName,
                'review_remark' => $remark,
                'form_data' => '',
                'billing_cycle' => null,
                'duration_days' => 0,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('提交失败：' . $e->getMessage());
        }

        $this->success('提交成功，我们会尽快审核', url('promotion/friendlinkApply', ['submitted' => 1]));
    }

    public function submit()
    {
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }
        $this->token();

        $slotCode = trim((string)$this->request->post('slot_code', ''));
        if ($slotCode === '' || $slotCode === 'friend_links') {
            $this->error('请选择有效的推广位');
        }

        $slot = PromotionSlot::where('code', $slotCode)->where('status', 'normal')->where('code', '<>', 'friend_links')->find();
        if (!$slot) {
            $this->error('所选推广位不可用');
        }

        $slotConfig = $this->normalizeSlotConfig($slot->getConfigArray());
        $billingCycle = trim((string)$this->request->post('billing_cycle', 'week'));
        if (!isset($slotConfig['pricing'][$billingCycle])) {
            $this->error('请选择有效的投放周期');
        }
        $durationDays = $this->getDurationDays($billingCycle);
        $amount = max(0, round((float)$slotConfig['pricing'][$billingCycle], 2));

        $contactName = trim((string)$this->request->post('contact_name', ''));
        $contactMobile = trim((string)$this->request->post('contact_mobile', ''));
        $companyName = trim((string)$this->request->post('company_name', ''));
        $remark = trim((string)$this->request->post('remark', ''));

        if ($contactName === '') {
            $this->error('请填写联系人');
        }
        if (!$this->isValidMobile($contactMobile)) {
            $this->error('请填写正确的联系电话');
        }

        $fieldsInput = $this->request->post('dynamic_fields/a', []);
        $fields = $this->validateDynamicFields($slotConfig['form_fields'], $fieldsInput);
        $title = trim((string)($fields['title'] ?? ''));
        $subTitle = trim((string)($fields['sub_title'] ?? ''));
        $linkUrl = trim((string)($fields['link_url'] ?? ''));
        $image = trim((string)($fields['image'] ?? ''));
        $categoryId = (int)($fields['category_id'] ?? 0);
        $pageScope = trim((string)($fields['page_scope'] ?? 'all'));
        if (!in_array($pageScope, ['all', 'home', 'category', 'channel'])) {
            $pageScope = 'all';
        }
        if ($title === '') {
            $title = (string)$slot['name'] . '推广申请';
        }
        if ($linkUrl !== '' && !$this->isValidUrl($linkUrl)) {
            $this->error('请填写正确的跳转链接');
        }

        $user = $this->auth ? $this->auth->getUser() : null;
        $userId = $user ? (int)$user->id : 0;
        $orderNo = $this->generateOrderNo($userId);
        $isFreeOrder = $amount <= 0;

        Db::startTrans();
        try {
            $item = new PromotionItem();
            $item->allowField(true)->save([
                'slot_id' => (int)$slot['id'],
                'title' => $title,
                'sub_title' => $subTitle,
                'link_url' => $linkUrl,
                'image' => $image,
                'category_id' => $categoryId,
                'page_scope' => $pageScope,
                'sort' => 0,
                'status' => $isFreeOrder ? 'pending' : 'draft',
                'review_status' => 'pending',
                'created_by_user_id' => $userId,
                'contact_name' => $contactName,
                'contact_mobile' => $contactMobile,
                'company_name' => $companyName,
                'form_data' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'billing_cycle' => $billingCycle,
                'duration_days' => $durationDays,
            ]);

            $order = new PromotionOrder();
            $order->allowField(true)->save([
                'order_no' => $orderNo,
                'item_id' => (int)$item['id'],
                'user_id' => $userId,
                'slot_id' => (int)$slot['id'],
                'category_id' => $categoryId,
                'amount' => $amount,
                'pay_status' => $isFreeOrder ? 'paid' : 'unpaid',
                'pay_type' => $isFreeOrder ? 'offline' : null,
                'pay_time' => $isFreeOrder ? time() : null,
                'pay_trade_no' => '',
                'paid_amount' => 0,
                'order_status' => 'pending',
                'contact_name' => $contactName,
                'contact_mobile' => $contactMobile,
                'company_name' => $companyName,
                'remark' => $remark,
                'offline_voucher' => '',
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error('提交失败：' . $e->getMessage());
        }

        $payload = [
            'order_id' => (int)$order['id'],
            'order_no' => (string)$order['order_no'],
            'amount' => (float)$order['amount'],
            'pay_status' => (string)$order['pay_status'],
        ];
        if ($this->request->isAjax()) {
            $this->success('提交成功', null, $payload);
        }
        $this->success('提交成功，请完成支付后等待审核', url('promotion/applySuccess', ['id' => (int)$order['id']]), $payload);
    }

    public function pay()
    {
        $orderId = (int)$this->request->param('order_id', $this->request->param('id', 0));
        if ($orderId < 1) {
            $this->error('订单不存在');
        }
        $order = PromotionOrder::where('id', $orderId)->where('user_id', (int)$this->auth->id)->find();
        if (!$order) {
            $this->error('订单不存在或无权限访问');
        }
        if ((string)$order['pay_status'] !== 'unpaid') {
            $this->success('订单已支付或已关闭', url('promotion/status', ['id' => $orderId]));
        }
        if ((float)$order['amount'] <= 0) {
            $this->success('该订单无需支付', url('promotion/status', ['id' => $orderId]));
        }

        $paytype = trim((string)$this->request->param('paytype', ''));
        $available = $this->getAvailablePaytypes();
        if (empty($available)) {
            $this->error('支付通道未配置，请联系管理员');
        }
        if ($paytype === '') {
            $paytype = $available[0];
        }
        if (!in_array($paytype, $available)) {
            $this->error('支付方式不可用');
        }

        $notifyUrl = $this->request->root(true) . url('promotion/epay', ['type' => 'notify', 'paytype' => $paytype], false);
        $returnUrl = $this->request->root(true) . url('promotion/epay', ['type' => 'return', 'paytype' => $paytype, 'order_id' => $orderId], false);

        try {
            $order->allowField(true)->save(['pay_type' => $paytype]);
            return Service::submitOrder([
                'amount' => (float)$order['amount'],
                'orderid' => (string)$order['order_no'],
                'type' => $paytype,
                'title' => '推广订单#' . (int)$order['id'],
                'notifyurl' => $notifyUrl,
                'returnurl' => $returnUrl,
                'method' => 'web',
            ]);
        } catch (\Throwable $e) {
            $this->error('支付发起失败：' . $e->getMessage());
        }
    }

    public function epay()
    {
        $type = trim((string)$this->request->param('type', ''));
        $paytype = trim((string)$this->request->param('paytype', ''));
        if (!in_array($type, ['notify', 'return']) || !in_array($paytype, ['wechat', 'alipay'])) {
            $this->error('参数错误');
        }

        if ($type === 'notify') {
            $pay = Service::checkNotify($paytype);
            if (!$pay) {
                return json(['code' => 'FAIL', 'message' => 'invalid sign'], 500, ['Content-Type' => 'application/json']);
            }

            $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();
            if (Service::isVersionV3() && $paytype === 'wechat') {
                $data = $data['resource']['ciphertext'] ?? [];
                if (isset($data['amount']['total'])) {
                    $data['total_fee'] = $data['amount']['total'];
                }
            }

            $orderNo = trim((string)($data['out_trade_no'] ?? ''));
            $tradeNo = $paytype === 'alipay' ? trim((string)($data['trade_no'] ?? '')) : trim((string)($data['transaction_id'] ?? ''));
            $paidAmount = $paytype === 'alipay' ? (float)($data['total_amount'] ?? 0) : ((float)($data['total_fee'] ?? 0) / 100);
            if ($orderNo !== '') {
                try {
                    $this->settlePromotionOrder($orderNo, $paytype, $tradeNo, $paidAmount);
                } catch (\Throwable $e) {
                    Log::error('Promotion notify settle failed', ['order_no' => $orderNo, 'error' => $e->getMessage()]);
                }
            }

            if (Service::isVersionV3()) {
                return $pay->success()->getBody()->getContents();
            }
            return $pay->success()->send();
        }

        $orderId = (int)$this->request->param('order_id', 0);
        if ($orderId < 1) {
            $orderNo = trim((string)$this->request->param('out_trade_no', ''));
            if ($orderNo !== '') {
                $order = PromotionOrder::where('order_no', $orderNo)->find();
                if ($order) {
                    $orderId = (int)$order['id'];
                }
            }
        }
        if ($orderId > 0) {
            return $this->redirect(url('promotion/status', ['id' => $orderId]));
        }
        return $this->redirect(url('user/promotionOrders'));
    }

    public function applySuccess()
    {
        $id = (int)$this->request->param('id', 0);
        if ($id < 1) {
            $this->error('订单不存在');
        }
        $orderTable = (new PromotionOrder())->getTable();
        $order = PromotionOrder::with(['item'])->where($orderTable . '.id', $id)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        if ((int)$order['user_id'] > 0) {
            if (!$this->auth->isLogin()) {
                $target = url('promotion/applySuccess', ['id' => $id], false, true);
                $this->error('请先登录后查看订单', url('user/login', ['url' => $target]));
            }
            if ((int)$order['user_id'] !== (int)$this->auth->id) {
                $this->error('无权访问该订单');
            }
        }

        $paytypeOptions = $this->getPaytypeOptions();
        $canPay = $this->auth->isLogin() && (int)$order['user_id'] === (int)$this->auth->id
            && (string)$order['pay_status'] === 'unpaid' && (float)$order['amount'] > 0 && !empty($paytypeOptions);

        $this->assign('order', $order);
        $this->assign('item', $order->item ?: null);
        $this->assign('canPay', $canPay);
        $this->assign('paytypeOptions', $paytypeOptions);
        $this->assign('title', '申请已提交');
        return $this->view->fetch('promotion/success');
    }

    public function status()
    {
        $id = (int)$this->request->param('id', 0);
        if ($id < 1) {
            $this->error('请提供订单ID');
        }
        $orderTable = (new PromotionOrder())->getTable();
        $order = PromotionOrder::with(['item'])->where($orderTable . '.id', $id)->find();
        if (!$order) {
            $this->error('未找到对应订单');
        }

        if ((int)$order['user_id'] > 0) {
            if (!$this->auth->isLogin()) {
                $target = url('promotion/status', ['id' => $id], false, true);
                $this->error('请先登录后查看订单', url('user/login', ['url' => $target]));
            }
            if ((int)$order['user_id'] !== (int)$this->auth->id) {
                $this->error('无权查看此订单');
            }
        }

        $paytypeOptions = $this->getPaytypeOptions();
        $canPay = $this->auth->isLogin() && (int)$order['user_id'] === (int)$this->auth->id
            && (string)$order['pay_status'] === 'unpaid' && (float)$order['amount'] > 0 && !empty($paytypeOptions);

        $this->assign('order', $order);
        $this->assign('item', $order->item ?: null);
        $this->assign('canPay', $canPay);
        $this->assign('paytypeOptions', $paytypeOptions);
        $this->assign('title', '订单状态查询');
        return $this->view->fetch('promotion/status');
    }

    protected function settlePromotionOrder($orderNo, $paytype, $tradeNo, $paidAmount)
    {
        Db::startTrans();
        try {
            $order = PromotionOrder::where('order_no', $orderNo)->lock(true)->find();
            if (!$order) {
                Db::rollback();
                return false;
            }
            if (in_array((string)$order['pay_status'], ['paid', 'offline_paid'])) {
                Db::commit();
                return true;
            }

            $amount = (float)$order['amount'];
            if ($amount > 0 && abs($paidAmount - $amount) > 0.01) {
                Log::error('Promotion amount mismatch', ['order_no' => $orderNo, 'paid_amount' => $paidAmount, 'amount' => $amount]);
                Db::rollback();
                return false;
            }

            $order->allowField(true)->save([
                'pay_status' => 'paid',
                'pay_type' => $paytype,
                'pay_trade_no' => $tradeNo,
                'paid_amount' => $paidAmount,
                'pay_time' => time(),
                'order_status' => 'pending',
            ]);

            $item = PromotionItem::where('id', (int)$order['item_id'])->lock(true)->find();
            if ($item && (string)$item['status'] === 'draft') {
                $item->allowField(true)->save(['status' => 'pending']);
            }
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function normalizeSlotConfig(array $config)
    {
        $pricing = ['week' => 0, 'month' => 0, 'quarter' => 0];
        $rawPricing = isset($config['pricing']) && is_array($config['pricing']) ? $config['pricing'] : [];
        foreach ($pricing as $cycle => $defaultValue) {
            $value = isset($rawPricing[$cycle]) ? (float)$rawPricing[$cycle] : $defaultValue;
            $pricing[$cycle] = $value >= 0 ? round($value, 2) : 0;
        }

        $fields = $this->sanitizeFormFields($config['form_fields'] ?? []);
        if (empty($fields)) {
            $fields = [
                ['name' => 'title', 'label' => '推广标题', 'type' => 'text', 'required' => 1, 'placeholder' => '请输入推广标题', 'maxlength' => 80, 'options' => []],
                ['name' => 'link_url', 'label' => '跳转链接', 'type' => 'url', 'required' => 0, 'placeholder' => '例如：https://www.example.com', 'maxlength' => 255, 'options' => []],
                ['name' => 'image', 'label' => '推广图片', 'type' => 'image', 'required' => 0, 'placeholder' => '请输入图片URL', 'maxlength' => 255, 'options' => []],
                ['name' => 'sub_title', 'label' => '副标题', 'type' => 'text', 'required' => 0, 'placeholder' => '可选', 'maxlength' => 120, 'options' => []],
            ];
        }

        return [
            'pricing' => $pricing,
            'require_login' => 1,
            'form_fields' => $fields,
        ];
    }

    protected function sanitizeFormFields($fields)
    {
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($fields)) {
            return [];
        }

        $allowTypes = ['text', 'url', 'textarea', 'number', 'select', 'image'];
        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string)($field['name'] ?? ''));
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{1,30}$/', $name)) {
                continue;
            }
            $type = strtolower(trim((string)($field['type'] ?? 'text')));
            if (!in_array($type, $allowTypes)) {
                $type = 'text';
            }
            $options = $type === 'select' ? $this->normalizeFieldOptions($field['options'] ?? []) : [];
            $result[] = [
                'name' => $name,
                'label' => trim((string)($field['label'] ?? $name)),
                'type' => $type,
                'required' => !empty($field['required']) ? 1 : 0,
                'placeholder' => trim((string)($field['placeholder'] ?? '')),
                'maxlength' => max(0, (int)($field['maxlength'] ?? 0)),
                'options' => $options,
            ];
        }
        return $result;
    }

    protected function validateDynamicFields(array $fieldDefs, array $input)
    {
        $result = [];
        foreach ($fieldDefs as $def) {
            $name = (string)($def['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $label = (string)($def['label'] ?? $name);
            $type = (string)($def['type'] ?? 'text');
            $required = !empty($def['required']);
            $maxlength = (int)($def['maxlength'] ?? 0);
            $value = isset($input[$name]) ? $input[$name] : '';
            if (is_array($value)) {
                $value = implode(',', array_filter(array_map('trim', $value), function ($v) {
                    return $v !== '';
                }));
            }
            $value = trim((string)$value);

            if ($required && $value === '') {
                $this->error('请填写：' . $label);
            }
            if ($value === '') {
                $result[$name] = '';
                continue;
            }
            if (($type === 'url' || $name === 'link_url') && !$this->isValidUrl($value)) {
                $this->error($label . '格式不正确');
            }
            if ($type === 'number' && !is_numeric($value)) {
                $this->error($label . '必须为数字');
            }
            if ($type === 'select') {
                $allow = [];
                foreach ((array)($def['options'] ?? []) as $option) {
                    $allow[] = (string)($option['value'] ?? '');
                }
                if (!empty($allow) && !in_array($value, $allow)) {
                    $this->error($label . '选项无效');
                }
            }
            if ($maxlength > 0 && mb_strlen($value) > $maxlength) {
                $this->error($label . '长度超出限制');
            }
            $result[$name] = $value;
        }
        return $result;
    }

    protected function normalizeFieldOptions($options)
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (is_array($decoded)) {
                $options = $decoded;
            } else {
                $tmp = [];
                foreach (preg_split('/[\r\n,]+/', $options) as $line) {
                    $line = trim((string)$line);
                    if ($line !== '') {
                        $tmp[] = ['value' => $line, 'label' => $line];
                    }
                }
                $options = $tmp;
            }
        }
        if (!is_array($options)) {
            return [];
        }
        $result = [];
        foreach ($options as $option) {
            if (is_array($option)) {
                $value = trim((string)($option['value'] ?? ''));
                $label = trim((string)($option['label'] ?? $value));
            } else {
                $value = trim((string)$option);
                $label = $value;
            }
            if ($value !== '') {
                $result[] = ['value' => $value, 'label' => $label];
            }
        }
        return $result;
    }

    protected function getAvailablePaytypes()
    {
        $addon = get_addon_info('epay');
        if (!$addon || empty($addon['state'])) {
            return [];
        }
        $config = get_addon_config('epay');
        $result = [];
        if ($this->isWechatPayEnabled($config['wechat'] ?? [])) {
            $result[] = 'wechat';
        }
        if ($this->isAlipayPayEnabled($config['alipay'] ?? [])) {
            $result[] = 'alipay';
        }
        return $result;
    }

    protected function getPaytypeOptions()
    {
        $map = ['wechat' => '微信支付', 'alipay' => '支付宝'];
        $result = [];
        foreach ($this->getAvailablePaytypes() as $paytype) {
            $result[] = ['value' => $paytype, 'label' => $map[$paytype] ?? $paytype];
        }
        return $result;
    }

    protected function isWechatPayEnabled(array $config)
    {
        $appId = trim((string)($config['appid'] ?? $config['app_id'] ?? ''));
        $mchId = trim((string)($config['mch_id'] ?? ''));
        $key = trim((string)($config['key'] ?? $config['key_v3'] ?? ''));
        return $appId !== '' && $mchId !== '' && $key !== '';
    }

    protected function isAlipayPayEnabled(array $config)
    {
        $appId = trim((string)($config['app_id'] ?? ''));
        $privateKey = trim((string)($config['private_key'] ?? ''));
        return $appId !== '' && $privateKey !== '';
    }

    protected function isValidMobile($mobile)
    {
        return preg_match('/^1[3-9]\d{9}$/', (string)$mobile) === 1;
    }

    protected function isValidUrl($url)
    {
        return filter_var((string)$url, FILTER_VALIDATE_URL) !== false;
    }

    protected function getDurationDays($cycle)
    {
        $map = ['week' => 7, 'month' => 30, 'quarter' => 90];
        return $map[$cycle] ?? 7;
    }

    protected function generateOrderNo($userId = 0)
    {
        $userId = (int)$userId;
        for ($i = 0; $i < 8; $i++) {
            $orderNo = 'PM' . date('YmdHis') . sprintf('%06d', $userId % 1000000) . mt_rand(1000, 9999);
            if (!PromotionOrder::where('order_no', $orderNo)->find()) {
                return $orderNo;
            }
        }
        return 'PM' . strtoupper(uniqid());
    }

    protected function getCdnUrl()
    {
        return Config::get('site.cdnurl') ?: '';
    }
}
