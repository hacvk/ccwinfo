<?php

namespace app\common\service;

use app\common\model\ExchangeGoods;
use app\common\model\ExchangeOrder;
use app\common\model\ScoreLog;
use app\common\model\User;
use think\Db;
use think\Exception;

class ExchangeService
{
    public static function submitOrder($userId, $goodsId, array $payload = [])
    {
        $userId = (int)$userId;
        $goodsId = (int)$goodsId;
        if ($userId <= 0 || $goodsId <= 0) {
            throw new Exception(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            /** @var ExchangeGoods $goods */
            $goods = ExchangeGoods::where('id', $goodsId)->lock(true)->find();
            if (!$goods) {
                throw new Exception(__('Goods not found'));
            }
            self::assertGoodsAvailable($goods, $userId);

            $orderNo = self::generateOrderNo($userId);
            $pointsCost = (int)$goods['points_cost'];
            $needShipping = (int)$goods['need_shipping'] === 1 || (string)$goods['goods_type'] === 'physical';
            $address = $needShipping ? self::validateShippingAddress($payload) : self::emptyAddressPayload();

            $snapshot = [
                'id'            => (int)$goods['id'],
                'title'         => (string)$goods['title'],
                'image'         => (string)$goods['image'],
                'goods_type'    => (string)$goods['goods_type'],
                'virtual_mode'  => (string)$goods['virtual_mode'],
                'virtual_source'=> (string)$goods['virtual_source'],
                'points_cost'   => $pointsCost,
                'need_shipping' => $needShipping ? 1 : 0,
            ];

            $isInternalAuto = self::isInternalAutoDeliver($goods);
            $deliveryContent = '';
            $orderStatus = 'pending';
            $pointsStatus = 'undeducted';
            $deliverTime = null;

            if ((int)$goods['stock'] < 1) {
                throw new Exception(__('Insufficient goods stock'));
            }
            $goods->setDec('stock', 1);
            if ($isInternalAuto) {
                $cardType = (string)$goods['virtual_mode'] === 'internal_recharge_card' ? 'recharge' : 'discount';
                $card = RechargeCardService::issueCardToUser($cardType, $userId, $orderNo);
                self::changeUserScore($userId, -$pointsCost, '积分兑换 #' . $orderNo);

                $deliveryContent = '卡号: ' . $card['card_no'] . PHP_EOL . '卡密: ' . $card['card_secret'];
                $orderStatus = 'delivered';
                $pointsStatus = 'deducted';
                $deliverTime = time();
            } else {
                $goods->setInc('locked_stock', 1);
            }

            $order = new ExchangeOrder();
            $order->allowField(true)->save(array_merge([
                'order_no'         => $orderNo,
                'user_id'          => $userId,
                'goods_id'         => $goodsId,
                'goods_snapshot'   => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'points_cost'      => $pointsCost,
                'points_status'    => $pointsStatus,
                'order_status'     => $orderStatus,
                'delivery_content' => $deliveryContent,
                'cancel_reason'    => '',
                'admin_remark'     => '',
                'deliver_time'     => $deliverTime,
                'cancel_time'      => null,
            ], $address));

            Db::commit();
            return $order;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function cancelOrderByUser($userId, $orderId, $reason = '')
    {
        $userId = (int)$userId;
        $orderId = (int)$orderId;
        $reason = trim((string)$reason);
        if ($userId <= 0 || $orderId <= 0) {
            throw new Exception(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            /** @var ExchangeOrder $order */
            $order = ExchangeOrder::where('id', $orderId)->where('user_id', $userId)->lock(true)->find();
            if (!$order) {
                throw new Exception(__('Order not found'));
            }
            if ((string)$order['order_status'] !== 'pending') {
                throw new Exception(__('Only pending order can be cancelled'));
            }

            $order->allowField(true)->save([
                'order_status'  => 'cancelled',
                'cancel_reason' => $reason,
                'cancel_time'   => time(),
            ]);
            self::releasePendingStock((int)$order['goods_id']);

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function adminDeliver($orderId, $deliveryContent, $adminRemark = '')
    {
        $orderId = (int)$orderId;
        $deliveryContent = trim((string)$deliveryContent);
        $adminRemark = trim((string)$adminRemark);
        if ($orderId <= 0 || $deliveryContent === '') {
            throw new Exception(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            /** @var ExchangeOrder $order */
            $order = ExchangeOrder::where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new Exception(__('Order not found'));
            }
            if ((string)$order['order_status'] !== 'pending') {
                throw new Exception(__('Only pending order can be delivered'));
            }

            self::deductWhenNeeded($order);

            $order->allowField(true)->save([
                'order_status'     => 'delivered',
                'delivery_content' => $deliveryContent,
                'admin_remark'     => $adminRemark,
                'deliver_time'     => time(),
            ]);
            self::releaseLockedOnly((int)$order['goods_id']);

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function adminShip($orderId, $deliveryContent, $adminRemark = '')
    {
        $orderId = (int)$orderId;
        $deliveryContent = trim((string)$deliveryContent);
        $adminRemark = trim((string)$adminRemark);
        if ($orderId <= 0 || $deliveryContent === '') {
            throw new Exception(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            /** @var ExchangeOrder $order */
            $order = ExchangeOrder::where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new Exception(__('Order not found'));
            }
            if ((string)$order['order_status'] !== 'pending') {
                throw new Exception(__('Only pending order can be shipped'));
            }

            self::deductWhenNeeded($order);

            $order->allowField(true)->save([
                'order_status'     => 'shipped',
                'delivery_content' => $deliveryContent,
                'admin_remark'     => $adminRemark,
                'deliver_time'     => time(),
            ]);
            self::releaseLockedOnly((int)$order['goods_id']);

            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function adminReject($orderId, $reason, $adminRemark = '')
    {
        $orderId = (int)$orderId;
        $reason = trim((string)$reason);
        $adminRemark = trim((string)$adminRemark);
        if ($orderId <= 0 || $reason === '') {
            throw new Exception(__('Invalid parameters'));
        }

        Db::startTrans();
        try {
            /** @var ExchangeOrder $order */
            $order = ExchangeOrder::where('id', $orderId)->lock(true)->find();
            if (!$order) {
                throw new Exception(__('Order not found'));
            }
            if (in_array((string)$order['order_status'], ['cancelled', 'rejected'], true)) {
                throw new Exception(__('Order already closed'));
            }

            $update = [
                'order_status'  => 'rejected',
                'cancel_reason' => $reason,
                'admin_remark'  => $adminRemark,
                'cancel_time'   => time(),
            ];
            if ((string)$order['order_status'] === 'pending') {
                self::releasePendingStock((int)$order['goods_id']);
            } elseif ((string)$order['points_status'] === 'deducted') {
                self::changeUserScore((int)$order['user_id'], (int)$order['points_cost'], '兑换订单驳回返还 #' . $order['order_no']);
                $update['points_status'] = 'refunded';
            }

            $order->allowField(true)->save($update);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected static function deductWhenNeeded(ExchangeOrder $order)
    {
        if ((string)$order['points_status'] === 'deducted') {
            return true;
        }
        $points = (int)$order['points_cost'];
        if ($points > 0) {
            self::changeUserScore((int)$order['user_id'], -$points, '兑换订单交付 #' . $order['order_no']);
        }
        $order->allowField(true)->save(['points_status' => 'deducted']);
        return true;
    }

    protected static function releasePendingStock($goodsId)
    {
        $goodsId = (int)$goodsId;
        if ($goodsId <= 0) {
            return;
        }
        Db::name('exchange_goods')->where('id', $goodsId)->setInc('stock', 1);
        Db::name('exchange_goods')->where('id', $goodsId)->where('locked_stock', '>', 0)->setDec('locked_stock', 1);
    }

    protected static function releaseLockedOnly($goodsId)
    {
        $goodsId = (int)$goodsId;
        if ($goodsId <= 0) {
            return;
        }
        Db::name('exchange_goods')->where('id', $goodsId)->where('locked_stock', '>', 0)->setDec('locked_stock', 1);
    }

    protected static function assertGoodsAvailable(ExchangeGoods $goods, $userId)
    {
        if ((string)$goods['status'] !== 'normal') {
            throw new Exception(__('Goods unavailable'));
        }
        $now = time();
        if ((int)$goods['start_time'] > 0 && (int)$goods['start_time'] > $now) {
            throw new Exception(__('Goods not started'));
        }
        if ((int)$goods['end_time'] > 0 && (int)$goods['end_time'] < $now) {
            throw new Exception(__('Goods expired'));
        }
        if ((int)$goods['stock'] < 1) {
            throw new Exception(__('Insufficient goods stock'));
        }
        $limit = (int)$goods['per_user_limit'];
        if ($limit > 0) {
            $usedCount = ExchangeOrder::where('user_id', $userId)
                ->where('goods_id', (int)$goods['id'])
                ->where('order_status', 'not in', ['cancelled', 'rejected'])
                ->count();
            if ((int)$usedCount >= $limit) {
                throw new Exception(__('User limit reached'));
            }
        }
    }

    protected static function validateShippingAddress(array $payload)
    {
        $provinceId = (int)($payload['province_id'] ?? 0);
        $cityId = (int)($payload['city_id'] ?? 0);
        $districtId = (int)($payload['district_id'] ?? 0);
        $provinceName = trim((string)($payload['province_name'] ?? ''));
        $cityName = trim((string)($payload['city_name'] ?? ''));
        $districtName = trim((string)($payload['district_name'] ?? ''));
        $addressDetail = trim((string)($payload['address_detail'] ?? ''));
        $receiverName = trim((string)($payload['receiver_name'] ?? ''));
        $receiverMobile = trim((string)($payload['receiver_mobile'] ?? ''));

        if ($provinceId <= 0 || $cityId <= 0 || $districtId <= 0) {
            throw new Exception(__('Please select full province/city/district'));
        }
        if ($provinceName === '' || $cityName === '' || $districtName === '') {
            throw new Exception(__('Address area name missing'));
        }
        if (mb_strpos($provinceName, '山东') !== 0) {
            throw new Exception(__('Only Shandong province address is supported'));
        }
        if ($addressDetail === '' || $receiverName === '' || $receiverMobile === '') {
            throw new Exception(__('Shipping address is incomplete'));
        }

        return [
            'province_id'    => $provinceId,
            'city_id'        => $cityId,
            'district_id'    => $districtId,
            'province_name'  => $provinceName,
            'city_name'      => $cityName,
            'district_name'  => $districtName,
            'address_detail' => $addressDetail,
            'receiver_name'  => $receiverName,
            'receiver_mobile'=> $receiverMobile,
        ];
    }

    protected static function emptyAddressPayload()
    {
        return [
            'province_id'     => 0,
            'city_id'         => 0,
            'district_id'     => 0,
            'province_name'   => '',
            'city_name'       => '',
            'district_name'   => '',
            'address_detail'  => '',
            'receiver_name'   => '',
            'receiver_mobile' => '',
        ];
    }

    protected static function isInternalAutoDeliver(ExchangeGoods $goods)
    {
        if ((string)$goods['goods_type'] !== 'virtual') {
            return false;
        }
        if ((string)$goods['virtual_source'] !== 'inside') {
            return false;
        }
        return in_array((string)$goods['virtual_mode'], ['internal_recharge_card', 'internal_discount_card'], true);
    }

    protected static function generateOrderNo($userId)
    {
        return 'EX' . date('YmdHis') . sprintf('%08d', (int)$userId) . mt_rand(1000, 9999);
    }

    protected static function changeUserScore($userId, $deltaScore, $memo)
    {
        $userId = (int)$userId;
        $deltaScore = (int)$deltaScore;
        if ($userId <= 0 || $deltaScore === 0) {
            return true;
        }

        $user = Db::name('user')->where('id', $userId)->lock(true)->find();
        if (!$user) {
            throw new Exception(__('User not found'));
        }

        $before = (int)$user['score'];
        $after = $before + $deltaScore;
        if ($after < 0) {
            throw new Exception(__('User score not enough'));
        }
        $level = User::nextlevel($after);

        Db::name('user')->where('id', $userId)->update([
            'score'      => $after,
            'level'      => $level,
            'updatetime' => time(),
        ]);
        ScoreLog::create([
            'user_id' => $userId,
            'score'   => $deltaScore,
            'before'  => $before,
            'after'   => $after,
            'memo'    => $memo,
        ]);

        return true;
    }
}

