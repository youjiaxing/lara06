<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/6/4 10:56
 */

namespace App\Services;

use App\Events\InstallmentPaidEvent;
use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Jobs\InstallmentRefund;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Order;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallmentService
{
    /**
     * 创建订单分期
     *
     * @param Order $order
     * @param int   $count    分期数
     * @param float $feeRate  百分比数值, eg. 1.5, 表示总手续费 1.5%
     * @param float $fineRate 百分比数值, eg. 0.05, 表示逾期日息 0.05%
     *
     * @return Installment
     * @throws \Throwable
     * @throws InvalidRequestException
     */
    public function createInstallment(Order $order, int $count, float $feeRate, float $fineRate)
    {
        // 订单状态验证
        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException("订单已支付或关闭");
        }

        // 每期还款间隔天数, 第一期从明天凌晨开始
        $dueDateInterval = 30;

        // 由于在未付款前可重复设置分期数, 因此此处可能有旧的分期信息
        // 在付款第一期费用后, 订单会变为 "已支付", 因此只要是未支付状态就可以一直重复设置分期数

        $installment = DB::transaction(
            function () use ($order, $count, $feeRate, $fineRate, $dueDateInterval) {
                // 移除旧的分期信息(由于外键的存在, 对应的 installment_items 会一并移除)
                Installment::query()->where('order_id', $order->id)->where('status', Installment::STATUS_PENDING)->delete();

                $installment = new Installment(
                    [
                        'no' => Installment::generateNo(),
                        'base_amount' => $order->total_amount,
                        'count' => $count,
                        'fee_rate' => $feeRate,
                        'fine_rate' => $fineRate,
                        'status' => Installment::STATUS_PENDING,
                    ]
                );
                $installment->user()->associate($order->user_id);
                $installment->order()->associate($order);
                $installment->save();

                $feeAndAmount = $this->getFeeAndAmount($order->total_amount, $count, $feeRate);

                for ($sequence = 1; $sequence <= $count; $sequence++) {
                    $isLast = $sequence === $count;
                    /* @var InstallmentItem $installmentItem */
                    $installmentItem = $installment->items()->create(
                        [
                            'sequence' => $sequence,
                            'base_amount' => $isLast ? $feeAndAmount['lastAmount'] : $feeAndAmount['avgAmount'],
                            'fee' => $isLast ? $feeAndAmount['lastFee'] : $feeAndAmount['avgFee'],
                            // 第一期的还款截止日期是明天凌晨0点
                            'due_date' => Carbon::tomorrow()->addDays(($sequence - 1) * $dueDateInterval),
                            'refund_status' => InstallmentItem::REFUND_STATUS_PENDING,
                        ]
                    );
                }

                Log::debug(
                    sprintf(
                        "订单分期, 订单基础总金额: %.4f, 订单每期基础金额: %.4f, 最后一期基础金额: %.4f. 总手续费: %.4f, 每期手续费: %.4f, 最后一期手续费: %.4f",
                        $feeAndAmount['totalAmount'],
                        $feeAndAmount['avgAmount'],
                        $feeAndAmount['lastAmount'],
                        $feeAndAmount['totalFee'],
                        $feeAndAmount['avgFee'],
                        $feeAndAmount['lastFee']
                    )
                );

                return $installment;
            }
        );

        return $installment;
    }

    /**
     * 计算订单金额对应分期下的费率
     *
     * @param float $totalAmount
     * @param int   $count
     * @param float $feeRate
     *
     * @return array
     */
    public function getFeeAndAmount(float $totalAmount, int $count, float $feeRate)
    {
        // 总的基础订单金额
        $totalAmountDecimal = BigDecimal::of($totalAmount);
        // 每期的基础订单金额
        $avgAmountDecimal = $totalAmountDecimal->dividedBy($count, 2, RoundingMode::FLOOR);
        // 最后一期的基础订单金额
        $lastAmountDecimal = $totalAmountDecimal->minus($avgAmountDecimal->multipliedBy($count - 1));

        // 总手续费
        $totalFeeDecimal = $totalAmountDecimal->multipliedBy($feeRate)->dividedBy(100, 2, RoundingMode::CEILING);
        // 每期平均手续费
        $avgFeeDecimal = $totalFeeDecimal->dividedBy($count, 2, RoundingMode::FLOOR);
        // 最后一期手续费
        $lastFeeDecimal = $totalFeeDecimal->minus($avgFeeDecimal->multipliedBy($count - 1));

        return [
            'totalAmount' => $totalAmountDecimal->toFloat(),
            'avgAmount' => $avgAmountDecimal->toFloat(),
            'lastAmount' => $lastAmountDecimal->toFloat(),

            'totalFee' => $totalFeeDecimal->toFloat(),
            'avgFee' => $avgFeeDecimal->toFloat(),
            'lastFee' => $lastFeeDecimal->toFloat(),
        ];
    }

    public function calcFine(Installment $installment, InstallmentItem $item)
    {
        if ($item->due_date->isFuture()) {
            return 0;
        }

        $dueDays = $item->due_date->diffInDays();
        if ($dueDays <= 0) {
            return 0;
        }

        $fineRate = $installment->fine_rate;
        $baseAndFee = BigDecimal::of($item->base_amount)->plus($item->fee);
        $fine = $baseAndFee->multipliedBy($fineRate)->multipliedBy($dueDays)->dividedBy(100, 2, RoundingMode::CEILING);
        if ($fine->isGreaterThan($baseAndFee)) {
            $fine = $baseAndFee;
        }

        return $fine->toScale(2, RoundingMode::CEILING)->toFloat();
    }

    /**
     * 支付分期订单
     *
     * @param InstallmentItem $installmentItem
     * @param string          $paymentMethod
     * @param string          $paymentNo
     *
     * @throws \Throwable
     */
    public function paid(InstallmentItem $installmentItem, string $paymentMethod, string $paymentNo)
    {
        if ($installmentItem->paid_at) {
            throw new InvalidRequestException("分期订单重复支付");
        }

        DB::transaction(
            function () use ($installmentItem, $paymentNo, $paymentMethod) {
                $installment = $installmentItem->installment;
                $order = $installment->order;
                $now = Carbon::now();

                // 第一期
                if ($installmentItem->sequence == 1) {
                    $order->update(['paid_at' => $now, 'payment_method' => Order::PAYMENT_METHOD_INSTALLMENT, 'payment_no' => $installment->no]);
                    $installment->update(['status' => Installment::STATUS_REPAYING]);
                } // 最后一期
                elseif ($installmentItem->sequence == $installment->count) {
                    $installment->update(['status' => Installment::STATUS_FINISHED]);
                }

                $installmentItem->update(
                    [
                        'paid_at' => $now,
                        'payment_method' => $paymentMethod,
                        'payment_no' => $paymentNo
                    ]
                );
            }
        );

        event(new InstallmentPaidEvent($installmentItem));
    }

    /**
     * 分期付款的订单退款
     *
     * @param Installment $installment
     *
     * @throws InternalException
     * @throws InvalidRequestException
     */
    public function refund(Installment $installment)
    {
        $order = $installment->order;
        if ($installment->status == Installment::STATUS_PENDING) {
            throw new InvalidRequestException("订单无需退款");
        }

        // 修改订单状态为
        $order->update(
            [
                'refund_status' => Order::REFUND_STATUS_PROCESSING,
                'refund_no' => Order::getAvailableRefundNo(),
            ]
        );

        dispatch(new InstallmentRefund($installment));
    }

    public function refundAlipay(Order $order, InstallmentItem $item)
    {
        $ret = app('alipay')->refund(
            [
                'out_trade_no' => $item->no, // 之前的订单流水号
                'refund_amount' => $item->base_amount, // 退款金额，单位元, 分歧在只退回本金
                'out_request_no' => $item->refund_no, // 退款订单号
            ]
        );
        // 根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
        if ($ret->sub_code) {
            $item->update(
                [
                    'refund_status' => InstallmentItem::REFUND_STATUS_FAILED,
                ]
            );
        } else {
            $item->update(
                [
                    'refund_status' => InstallmentItem::REFUND_STATUS_SUCCESS,
                ]
            );
        }
    }

    //TODO
    public function refundWeChat(Order $order, InstallmentItem $item)
    {
        // app('wechat_pay')->refund(
        //     [
        //         'out_trade_no' => $item->no, // 之前的订单流水号
        //         'total_fee' => $item->total_amount * 100, //原订单金额，单位分
        //         'refund_fee' => $item->base_amount * 100, // 要退款的订单金额，单位分
        //         'out_refund_no' => $item->refund_no, // 退款订单号
        //         // 微信支付的退款结果并不是实时返回的，而是通过退款回调来通知，因此这里需要配上退款回调接口地址
        //         'notify_url' => ngrok_route('installments.wechat.notify'),
        //     ]
        // );
        // 将订单状态改成退款中
        $item->update(
            [
                'refund_status' => InstallmentItem::REFUND_STATUS_PROCESSING,
            ]
        );
    }

    /**
     * 确认处于退款中的分期订单是否已全部退款完毕
     *
     * @param Installment $installment
     */
    public function checkRefunding(Installment $installment)
    {
        if ($installment->order->refund_status != Order::REFUND_STATUS_PROCESSING) {
            Log::warning(__METHOD__ . " 分期订单未处于 '退款中' 状态");
            return;
        }

        $refundSuccess = true;
        foreach ($installment->items as $item) {
            if ($item->paid_at && $item->refund_status != InstallmentItem::REFUND_STATUS_SUCCESS) {
                $refundSuccess = false;
                break;
            }
        }

        if (!$refundSuccess) {
            return;
        }

        $installment->order->update(['refund_status' => Order::REFUND_STATUS_SUCCESS]);
    }
}