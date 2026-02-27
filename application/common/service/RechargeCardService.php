<?php

namespace app\common\service;

use app\common\model\RechargeCard;
use think\Db;
use think\Exception;

class RechargeCardService
{
    public static function reserveDiscountCard($cardNo, $cardSecret, $amount, $userId, $orderId)
    {
        $cardNo = strtoupper(trim((string)$cardNo));
        $cardSecret = trim((string)$cardSecret);
        $amount = round((float)$amount, 2);
        $userId = (int)$userId;
        $orderId = trim((string)$orderId);
        if ($cardNo === '' || $cardSecret === '' || $orderId === '') {
            throw new Exception(__('Invalid card parameters'));
        }

        Db::startTrans();
        try {
            /** @var RechargeCard $card */
            $card = RechargeCard::where('card_no', $cardNo)->lock(true)->find();
            if (!$card) {
                throw new Exception(__('Discount card not found'));
            }
            self::assertCardSecret($card, $cardSecret);
            self::assertCardAvailable($card, 'discount', $userId);
            if ((float)$card['bonus_rate'] <= 0) {
                throw new Exception(__('Invalid discount card configuration'));
            }
            if ($amount < (float)$card['min_recharge']) {
                throw new Exception(__('Recharge amount does not meet discount card minimum'));
            }

            $bonus = self::calcDiscountBonus($card, $amount);
            if ($bonus <= 0) {
                throw new Exception(__('Discount card bonus is zero'));
            }

            $card->allowField(true)->save([
                'status'          => 'reserved',
                'owner_user_id'   => $userId,
                'reserve_orderid' => $orderId,
            ]);
            Db::commit();

            return [
                'card'  => $card,
                'bonus' => $bonus,
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function markDiscountCardUsed($cardId, $orderId, $userId)
    {
        $cardId = (int)$cardId;
        if ($cardId <= 0) {
            return false;
        }

        Db::startTrans();
        try {
            /** @var RechargeCard $card */
            $card = RechargeCard::where('id', $cardId)->lock(true)->find();
            if (!$card) {
                throw new Exception(__('Discount card not found'));
            }
            if ((string)$card['card_type'] !== 'discount') {
                throw new Exception(__('Card type mismatch'));
            }
            if ((string)$card['status'] === 'used') {
                Db::commit();
                return true;
            }
            if ((string)$card['status'] !== 'reserved') {
                throw new Exception(__('Discount card status invalid'));
            }
            if ($orderId !== '' && (string)$card['reserve_orderid'] !== (string)$orderId) {
                throw new Exception(__('Discount card reserve order mismatch'));
            }

            $card->allowField(true)->save([
                'status'        => 'used',
                'used_user_id'  => (int)$userId,
                'used_orderid'  => (string)$orderId,
                'used_time'     => time(),
                'reserve_orderid' => (string)$orderId,
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function releaseDiscountCard($cardId, $orderId = '')
    {
        $cardId = (int)$cardId;
        if ($cardId <= 0) {
            return false;
        }

        Db::startTrans();
        try {
            /** @var RechargeCard $card */
            $card = RechargeCard::where('id', $cardId)->lock(true)->find();
            if (!$card) {
                throw new Exception(__('Discount card not found'));
            }
            if ((string)$card['card_type'] !== 'discount') {
                throw new Exception(__('Card type mismatch'));
            }
            if ((string)$card['status'] !== 'reserved') {
                Db::commit();
                return true;
            }
            if ($orderId !== '' && (string)$card['reserve_orderid'] !== (string)$orderId) {
                throw new Exception(__('Discount card reserve order mismatch'));
            }

            $restoreStatus = (int)$card['owner_user_id'] > 0 ? 'issued' : 'unused';
            $card->allowField(true)->save([
                'status'          => $restoreStatus,
                'reserve_orderid' => '',
            ]);
            Db::commit();
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function consumeRechargeCard($cardNo, $cardSecret, $amount, $userId, $orderId)
    {
        $cardNo = strtoupper(trim((string)$cardNo));
        $cardSecret = trim((string)$cardSecret);
        $amount = round((float)$amount, 2);
        $userId = (int)$userId;
        $orderId = trim((string)$orderId);
        if ($cardNo === '' || $cardSecret === '' || $orderId === '') {
            throw new Exception(__('Invalid card parameters'));
        }

        Db::startTrans();
        try {
            /** @var RechargeCard $card */
            $card = RechargeCard::where('card_no', $cardNo)->lock(true)->find();
            if (!$card) {
                throw new Exception(__('Recharge card not found'));
            }
            self::assertCardSecret($card, $cardSecret);
            self::assertCardAvailable($card, 'recharge', $userId);

            $faceAmount = round((float)$card['face_amount'], 2);
            if (abs($faceAmount - $amount) > 0.01) {
                throw new Exception(__('Recharge amount must match card face amount'));
            }

            $card->allowField(true)->save([
                'status'          => 'used',
                'used_user_id'    => $userId,
                'used_orderid'    => $orderId,
                'reserve_orderid' => $orderId,
                'used_time'       => time(),
            ]);

            Db::commit();
            return $card;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function issueCardToUser($cardType, $userId, $orderId)
    {
        $cardType = trim((string)$cardType);
        $userId = (int)$userId;
        $orderId = trim((string)$orderId);
        if (!in_array($cardType, ['recharge', 'discount'], true)) {
            throw new Exception(__('Invalid card type'));
        }
        if ($userId <= 0 || $orderId === '') {
            throw new Exception(__('Invalid issue parameters'));
        }

        Db::startTrans();
        try {
            $now = time();
            /** @var RechargeCard $card */
            $card = RechargeCard::where('card_type', $cardType)
                ->where('status', 'unused')
                ->where(function ($query) use ($now) {
                    $query->whereNull('expiretime')
                        ->whereOr('expiretime', 0)
                        ->whereOr('expiretime', '>', $now);
                })
                ->order('id asc')
                ->lock(true)
                ->find();
            if (!$card) {
                throw new Exception(__('No available internal card stock'));
            }

            $card->allowField(true)->save([
                'status'          => 'issued',
                'owner_user_id'   => $userId,
                'reserve_orderid' => $orderId,
            ]);

            Db::commit();
            return $card;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function batchGenerate($cardType, $count, array $options = [])
    {
        $cardType = trim((string)$cardType);
        $count = (int)$count;
        if (!in_array($cardType, ['recharge', 'discount'], true)) {
            throw new Exception(__('Invalid card type'));
        }
        if ($count < 1 || $count > 5000) {
            throw new Exception(__('Generate count must be between 1 and 5000'));
        }

        $batchNo = trim((string)($options['batch_no'] ?? ''));
        if ($batchNo === '') {
            $batchNo = 'B' . date('YmdHis') . mt_rand(100, 999);
        }

        $faceAmount = round((float)($options['face_amount'] ?? 0), 2);
        $bonusRate = round((float)($options['bonus_rate'] ?? 0), 4);
        $minRecharge = round((float)($options['min_recharge'] ?? 0), 2);
        $maxBonus = round((float)($options['max_bonus'] ?? 0), 2);
        $expiretime = isset($options['expiretime']) && $options['expiretime'] !== '' ? (int)$options['expiretime'] : null;

        if ($cardType === 'recharge') {
            if ($faceAmount <= 0) {
                throw new Exception(__('Recharge card face amount must be greater than zero'));
            }
            $bonusRate = 0;
            $minRecharge = 0;
            $maxBonus = 0;
        } else {
            if ($bonusRate <= 0) {
                throw new Exception(__('Discount card bonus rate must be greater than zero'));
            }
            if ($minRecharge < 0 || $maxBonus < 0) {
                throw new Exception(__('Discount card config invalid'));
            }
            $faceAmount = 0;
        }

        $rows = [];
        $now = time();
        for ($i = 0; $i < $count; $i++) {
            [$cardNo, $cardSecret] = self::buildCardPair($cardType, $batchNo, $i);
            $rows[] = [
                'card_no'        => $cardNo,
                'card_secret'    => $cardSecret,
                'card_type'      => $cardType,
                'face_amount'    => $faceAmount,
                'bonus_rate'     => $bonusRate,
                'min_recharge'   => $minRecharge,
                'max_bonus'      => $maxBonus,
                'batch_no'       => $batchNo,
                'owner_user_id'  => 0,
                'status'         => 'unused',
                'expiretime'     => $expiretime,
                'reserve_orderid'=> '',
                'used_user_id'   => 0,
                'used_orderid'   => '',
                'used_time'      => null,
                'createtime'     => $now,
                'updatetime'     => $now,
            ];
        }

        Db::startTrans();
        try {
            $affected = Db::name('recharge_card')->insertAll($rows);
            Db::commit();
            return [
                'batch_no' => $batchNo,
                'count'    => (int)$affected,
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    public static function calcDiscountBonus($card, $amount)
    {
        $amount = round((float)$amount, 2);
        $bonusRate = (float)($card['bonus_rate'] ?? 0);
        $maxBonus = round((float)($card['max_bonus'] ?? 0), 2);
        $bonus = round($amount * $bonusRate, 2);
        if ($maxBonus > 0 && $bonus > $maxBonus) {
            $bonus = $maxBonus;
        }
        return max(0, $bonus);
    }

    protected static function assertCardSecret(RechargeCard $card, $secret)
    {
        if (!hash_equals((string)$card['card_secret'], (string)$secret)) {
            throw new Exception(__('Card secret incorrect'));
        }
    }

    protected static function assertCardAvailable(RechargeCard $card, $expectedType, $userId = 0)
    {
        if ((string)$card['card_type'] !== $expectedType) {
            throw new Exception(__('Card type mismatch'));
        }
        $status = (string)$card['status'];
        if ($status === 'unused') {
            // ok
        } elseif ($status === 'issued') {
            if ((int)$card['owner_user_id'] <= 0 || (int)$card['owner_user_id'] !== (int)$userId) {
                throw new Exception(__('Card does not belong to current user'));
            }
        } else {
            throw new Exception(__('Card is not available'));
        }
        if ((int)$card['expiretime'] > 0 && (int)$card['expiretime'] < time()) {
            throw new Exception(__('Card is expired'));
        }
    }

    protected static function buildCardPair($cardType, $batchNo, $index)
    {
        $prefix = $cardType === 'recharge' ? 'RC' : 'DC';
        $cardNo = $prefix . strtoupper(substr(date('ymdHis') . md5($batchNo . $index . mt_rand(1000, 9999)), 0, 16));
        $cardSecret = strtoupper(substr(md5(uniqid((string)mt_rand(100000, 999999), true)), 0, 12));
        return [$cardNo, $cardSecret];
    }
}
