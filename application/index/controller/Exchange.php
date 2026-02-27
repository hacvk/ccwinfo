<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\ExchangeGoods;
use app\common\model\ExchangeOrder;
use app\common\service\ExchangeService;
use think\Exception;

class Exchange extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = [];
    protected $noNeedRight = ['*'];

    public function index()
    {
        return $this->mall();
    }

    public function mall()
    {
        $this->request->filter(['strip_tags', 'trim']);
        $query = ExchangeGoods::where('status', 'normal')
            ->where('stock', '>', 0)
            ->order('weigh desc,id desc');
        $now = time();
        $query->where(function ($q) use ($now) {
            $q->whereNull('start_time')
                ->whereOr('start_time', 0)
                ->whereOr('start_time', '<=', $now);
        });
        $query->where(function ($q) use ($now) {
            $q->whereNull('end_time')
                ->whereOr('end_time', 0)
                ->whereOr('end_time', '>=', $now);
        });

        $list = $query->paginate(12, false, ['query' => $this->request->get()]);
        if ($this->request->isAjax()) {
            $rows = is_object($list) ? $list->items() : [];
            $this->success('', '', ['total' => $list->total(), 'rows' => $rows]);
        }

        $this->view->assign('list', $list);
        $this->view->assign('title', __('Points exchange mall'));
        return $this->view->fetch('exchange/index');
    }

    public function detail()
    {
        $id = (int)$this->request->param('id', 0);
        if ($id <= 0) {
            $this->error(__('Invalid parameters'));
        }

        $goods = ExchangeGoods::get($id);
        if (!$goods || (string)$goods['status'] !== 'normal') {
            $this->error(__('Goods not found'));
        }
        $now = time();
        if ((int)$goods['start_time'] > 0 && (int)$goods['start_time'] > $now) {
            $this->error(__('Goods not started'));
        }
        if ((int)$goods['end_time'] > 0 && (int)$goods['end_time'] < $now) {
            $this->error(__('Goods expired'));
        }
        if ((int)$goods['stock'] < 1) {
            $this->error(__('Goods sold out'));
        }

        if ($this->request->isAjax()) {
            $this->success('', '', $goods);
        }

        $this->view->assign('goods', $goods);
        $this->view->assign('title', __('Exchange detail'));
        return $this->view->fetch('exchange/detail');
    }

    public function submit()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->token();

        $goodsId = (int)$this->request->post('goods_id', 0);
        $payload = [
            'province_id'     => (int)$this->request->post('province_id', 0),
            'city_id'         => (int)$this->request->post('city_id', 0),
            'district_id'     => (int)$this->request->post('district_id', 0),
            'province_name'   => trim((string)$this->request->post('province_name', '')),
            'city_name'       => trim((string)$this->request->post('city_name', '')),
            'district_name'   => trim((string)$this->request->post('district_name', '')),
            'address_detail'  => trim((string)$this->request->post('address_detail', '')),
            'receiver_name'   => trim((string)$this->request->post('receiver_name', '')),
            'receiver_mobile' => trim((string)$this->request->post('receiver_mobile', '')),
        ];

        try {
            $order = ExchangeService::submitOrder((int)$this->auth->id, $goodsId, $payload);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->error(__('Submit failed'));
        }

        $this->success(__('Submit success'), url('exchange/orders'), ['order_id' => (int)$order['id']]);
    }

    public function orders()
    {
        $query = ExchangeOrder::with(['goods'])
            ->where('user_id', (int)$this->auth->id)
            ->order('id desc');
        $list = $query->paginate(10, false, ['query' => $this->request->get()]);

        if ($this->request->isAjax()) {
            $rows = is_object($list) ? $list->items() : [];
            $this->success('', '', ['total' => $list->total(), 'rows' => $rows]);
        }

        $this->view->assign('orders', $list);
        $this->view->assign('title', __('My exchange orders'));
        return $this->view->fetch('exchange/orders');
    }

    public function cancel()
    {
        if (!$this->request->isPost()) {
            $this->error(__('Invalid parameters'));
        }
        $this->token();

        $orderId = (int)$this->request->post('id', 0);
        $reason = trim((string)$this->request->post('reason', ''));
        try {
            ExchangeService::cancelOrderByUser((int)$this->auth->id, $orderId, $reason);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            $this->error(__('Cancel failed'));
        }
        $this->success(__('Cancel success'));
    }
}

