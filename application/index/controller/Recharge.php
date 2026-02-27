<?php

namespace app\index\controller;

use addons\epay\library\Service;
use addons\recharge\library\Order;
use addons\recharge\model\MoneyLog;
use app\common\controller\Frontend;
use app\common\service\RechargeCardService;
use think\Exception;
use Yansongda\Pay\Pay;

class Recharge extends Frontend
{
    protected $layout = 'default';
    protected $noNeedLogin = ['epay'];
    protected $noNeedRight = ['*'];

    public function recharge()
    {
        $config = get_addon_config('recharge');
        $config = is_array($config) ? $config : [];

        $moneyList = [];
        $defaultMoney = isset($config['defaultmoney']) ? (string)$config['defaultmoney'] : '';
        $moneySource = isset($config['moneylist']) && is_array($config['moneylist']) ? $config['moneylist'] : [];
        foreach ($moneySource as $index => $item) {
            $moneyList[] = [
                'value'   => $item,
                'text'    => $index,
                'default' => (string)$item === $defaultMoney,
            ];
        }

        $manualPaytypes = $this->getRechargePaytypes($config);
        $availablePaytypes = $this->getAvailablePaytypes($manualPaytypes);
        $defaultPaytype = $this->getDefaultPaytype($availablePaytypes, $config['defaultpaytype'] ?? '');

        $paytypeList = [];
        foreach ($availablePaytypes as $item) {
            $labelMap = [
                'wechat' => __('Wechat pay'),
                'alipay' => __('Alipay pay'),
                'card'   => __('Recharge card pay'),
            ];
            $paytypeList[] = [
                'value'   => $item,
                'image'   => in_array($item, ['wechat', 'alipay']) ? '/assets/addons/recharge/img/' . $item . '.png' : '',
                'label'   => $labelMap[$item] ?? $item,
                'default' => $item === $defaultPaytype,
            ];
        }

        $this->view->assign('addonConfig', $config);
        $this->view->assign('moneyList', $moneyList);
        $this->view->assign('paytypeList', $paytypeList);
        $this->view->assign('defaultPaytype', $defaultPaytype);
        $this->view->assign('canRecharge', !empty($paytypeList));
        $this->view->assign('title', __('Recharge'));
        return $this->view->fetch();
    }

    public function moneylog()
    {
        $moneyloglist = MoneyLog::where(['user_id' => $this->auth->id])
            ->order('id desc')
            ->paginate(10);

        $this->view->assign('title', __('Balance log'));
        $this->view->assign('moneyloglist', $moneyloglist);
        return $this->view->fetch();
    }

    public function submit()
    {
        $money = $this->request->request('money/f');
        $paytype = trim((string)$this->request->request('paytype', ''));
        $rechargeCardNo = strtoupper(trim((string)$this->request->request('recharge_card_no', '')));
        $rechargeCardSecret = trim((string)$this->request->request('recharge_card_secret', ''));
        $discountCardNo = strtoupper(trim((string)$this->request->request('discount_card_no', '')));
        $discountCardSecret = trim((string)$this->request->request('discount_card_secret', ''));
        if ($money <= 0) {
            $this->error('Invalid recharge amount');
        }

        $config = get_addon_config('recharge');
        $config = is_array($config) ? $config : [];
        if (isset($config['minmoney']) && $money < (float)$config['minmoney']) {
            $this->error('Recharge amount can not be lower than ' . $config['minmoney']);
        }

        $manualPaytypes = $this->getRechargePaytypes($config);
        $availablePaytypes = $this->getAvailablePaytypes($manualPaytypes);
        if (empty($availablePaytypes)) {
            $this->error('No available payment channel');
        }

        if ($paytype === '') {
            $paytype = $this->getDefaultPaytype($availablePaytypes, $config['defaultpaytype'] ?? '');
        }
        if (!$paytype || !in_array($paytype, $availablePaytypes)) {
            $this->error('Pay type is not available');
        }

        if ($paytype !== 'card') {
            $info = get_addon_info('epay');
            if (!$info || !$info['state']) {
                $this->error('Please install and configure epay addon first');
            }
        }

        if ($paytype === 'card') {
            if ($discountCardNo !== '' || $discountCardSecret !== '') {
                $this->error('Recharge card and discount card can not be used together');
            }
            if ($rechargeCardNo === '' || $rechargeCardSecret === '') {
                $this->error('Please input recharge card no and secret');
            }
            try {
                $order = Order::createOrder($money, 'card', ['bonus_amount' => 0]);
                $card = RechargeCardService::consumeRechargeCard(
                    $rechargeCardNo,
                    $rechargeCardSecret,
                    $money,
                    (int)$this->auth->id,
                    (string)$order['orderid']
                );
                $order->allowField(true)->save([
                    'recharge_card_id' => (int)$card['id'],
                    'memo'             => 'pay by recharge card',
                ]);
                if (!Order::settle((string)$order['orderid'], $money, 'recharge-card')) {
                    throw new Exception('Recharge settle failed');
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
            } catch (\Throwable $e) {
                $this->error('Recharge failed');
            }
            $this->success('Recharge successful', url('recharge/moneylog'));
        }

        if (($discountCardNo === '' && $discountCardSecret !== '') || ($discountCardNo !== '' && $discountCardSecret === '')) {
            $this->error('Please input both discount card no and secret');
        }

        $discountCardId = 0;
        $order = null;
        try {
            $order = Order::createOrder($money, $paytype, ['bonus_amount' => 0]);
            if ($discountCardNo !== '' || $discountCardSecret !== '') {
                $reserveResult = RechargeCardService::reserveDiscountCard(
                    $discountCardNo,
                    $discountCardSecret,
                    $money,
                    (int)$this->auth->id,
                    (string)$order['orderid']
                );
                $discountCardId = (int)$reserveResult['card']['id'];
                $order->allowField(true)->save([
                    'discount_card_id' => $discountCardId,
                    'bonus_amount'     => round((float)$reserveResult['bonus'], 2),
                ]);
            }

            return Order::pay($order, $money, $paytype);
        } catch (Exception $e) {
            if ($discountCardId > 0 && $order) {
                try {
                    RechargeCardService::releaseDiscountCard($discountCardId, (string)$order['orderid']);
                } catch (\Throwable $throwable) {
                }
            }
            $this->error($e->getMessage());
        } catch (\Throwable $e) {
            if ($discountCardId > 0 && $order) {
                try {
                    RechargeCardService::releaseDiscountCard($discountCardId, (string)$order['orderid']);
                } catch (\Throwable $throwable) {
                }
            }
            $this->error('Submit failed');
        }
    }

    public function epay()
    {
        $type = trim((string)$this->request->param('type', ''));
        $paytype = trim((string)$this->request->param('paytype', ''));
        if (!in_array($type, ['notify', 'return']) || !in_array($paytype, ['wechat', 'alipay'])) {
            $this->error('Invalid params');
        }

        if ($type === 'notify') {
            $pay = Service::checkNotify($paytype);
            if (!$pay) {
                return json(['code' => 'FAIL', 'message' => 'invalid sign'], 500, ['Content-Type' => 'application/json']);
            }

            $data = Service::isVersionV3() ? $pay->callback() : $pay->verify();

            try {
                if (Service::isVersionV3() && $paytype === 'wechat') {
                    $data = $data['resource']['ciphertext'] ?? [];
                    if (isset($data['amount']['total'])) {
                        $data['total_fee'] = $data['amount']['total'];
                    }
                }

                $orderid = trim((string)($data['out_trade_no'] ?? ''));
                $payamount = $paytype === 'alipay'
                    ? (float)($data['total_amount'] ?? 0)
                    : ((float)($data['total_fee'] ?? 0) / 100);
                if ($orderid !== '' && $payamount > 0) {
                    \addons\recharge\library\Order::settle($orderid, $payamount);
                }
            } catch (\Throwable $e) {
                \think\Log::record('[recharge][notify] settle failed: ' . $e->getMessage(), 'error');
            }

            if (Service::isVersionV3()) {
                return $pay->success()->getBody()->getContents();
            }
            return $pay->success()->send();
        }

        $outTradeNo = trim((string)$this->request->param('out_trade_no', ''));
        if ($outTradeNo !== '') {
            try {
                $this->settleByReturn($paytype, $outTradeNo);
            } catch (\Throwable $e) {
                \think\Log::record('[recharge][return] settle failed: ' . $e->getMessage(), 'error');
            }
        }

        $this->success('Please return to user center to check payment status', url('user/index'));
    }

    protected function settleByReturn($paytype, $outTradeNo)
    {
        $order = \addons\recharge\model\Order::getByOrderid($outTradeNo);
        if (!$order || (string)$order['status'] === 'paid') {
            return;
        }

        $payamount = $this->queryPaidAmount($paytype, $outTradeNo);
        if ($payamount <= 0) {
            return;
        }

        \addons\recharge\library\Order::settle($outTradeNo, $payamount, 'return-fallback');
    }

    protected function queryPaidAmount($paytype, $outTradeNo)
    {
        if ($paytype === 'alipay') {
            $pay = Pay::alipay(Service::getConfig('alipay'));
            $result = $pay->find(['out_trade_no' => $outTradeNo]);
            if (($result['code'] ?? '') === '10000' && in_array(($result['trade_status'] ?? ''), ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
                return (float)($result['total_amount'] ?? 0);
            }
            return 0;
        }

        $pay = Pay::wechat(Service::getConfig('wechat'));
        if (Service::isVersionV3()) {
            $result = $pay->find(['out_trade_no' => $outTradeNo]);
            if (($result['trade_state'] ?? '') === 'SUCCESS') {
                return ((float)($result['amount']['total'] ?? 0)) / 100;
            }
            return 0;
        }

        $result = $pay->find($outTradeNo, 'scan');
        if (($result['trade_state'] ?? '') === 'SUCCESS' || ($result['trade_status'] ?? '') === 'SUCCESS') {
            return ((float)($result['total_fee'] ?? 0)) / 100;
        }
        return 0;
    }

    protected function getRechargePaytypes($config)
    {
        $raw = $config['paytypelist'] ?? '';
        $list = is_array($raw) ? $raw : explode(',', (string)$raw);
        $list = array_filter(array_map(function ($item) {
            return trim((string)$item);
        }, $list));
        $list = array_values(array_unique($list));
        return array_values(array_intersect($list, ['wechat', 'alipay', 'card']));
    }

    protected function getDefaultPaytype(array $availablePaytypes, $defaultPaytype = '')
    {
        $defaultPaytype = trim((string)$defaultPaytype);
        if ($defaultPaytype !== '' && in_array($defaultPaytype, $availablePaytypes)) {
            return $defaultPaytype;
        }
        return !empty($availablePaytypes) ? $availablePaytypes[0] : '';
    }

    protected function getAvailablePaytypes(array $manualPaytypes = [])
    {
        $result = [];
        if (in_array('card', $manualPaytypes, true)) {
            $result[] = 'card';
        }

        $addon = get_addon_info('epay');
        if (!$addon || empty($addon['state'])) {
            return $result;
        }

        $config = get_addon_config('epay');
        $config = is_array($config) ? $config : [];
        if (in_array('wechat', $manualPaytypes, true) && $this->isWechatPayEnabled($config['wechat'] ?? [])) {
            $result[] = 'wechat';
        }
        if (in_array('alipay', $manualPaytypes, true) && $this->isAlipayPayEnabled($config['alipay'] ?? [])) {
            $result[] = 'alipay';
        }
        return $result;
    }

    protected function isWechatPayEnabled(array $config)
    {
        $appId = trim((string)($config['appid'] ?? ($config['app_id'] ?? '')));
        $mchId = trim((string)($config['mch_id'] ?? ''));
        $key = trim((string)($config['key'] ?? ($config['key_v3'] ?? '')));
        return $appId !== '' && $mchId !== '' && $key !== '';
    }

    protected function isAlipayPayEnabled(array $config)
    {
        $appId = trim((string)($config['app_id'] ?? ''));
        $privateKey = trim((string)($config['private_key'] ?? ''));
        return $appId !== '' && $privateKey !== '';
    }
}
