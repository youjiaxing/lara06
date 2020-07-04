<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderService
{
    /**
     * 普通商品下单(从购物车)
     *
     * @param User            $user
     * @param UserAddress     $address
     * @param                 $remark
     * @param                 $items
     * @param CouponCode|null $coupon
     *
     * @return mixed
     * @throws CouponCodeUnavailableException
     */
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        // 如果传入了优惠券，则先检查是否可用
        if ($coupon) {
            // 但此时我们还没有计算出订单总金额，因此先不校验
            $coupon->checkAvailable($user);
        }
        // 开启一个数据库事务
        $order = \DB::transaction(
            function () use ($user, $address, $remark, $items, $coupon) {
                // 更新此地址的最后使用时间
                $address->update(['last_used_at' => Carbon::now()]);
                // 创建一个订单
                $order = new Order(
                    [
                        'type' => Order::TYPE_NORMAL,
                        'address' => $this->extractAddress($address),
                        'remark' => $remark,
                        'total_amount' => 0,
                    ]
                );
                // 订单关联到当前用户
                $order->user()->associate($user);
                // 写入数据库
                $order->save();

                $totalAmount = 0;
                // 遍历用户提交的 SKU
                foreach ($items as $data) {
                    $sku = ProductSku::find($data['sku_id']);
                    // 创建一个 OrderItem 并直接与当前订单关联
                    $item = $order->items()->make(
                        [
                            'amount' => $data['amount'],
                            'price' => $sku->price,
                        ]
                    );
                    $item->product()->associate($sku->product_id);
                    $item->productSku()->associate($sku);
                    $item->save();
                    $totalAmount += $sku->price * $data['amount'];
                    if ($sku->decreaseStock($data['amount']) <= 0) {
                        throw new InvalidRequestException('该商品库存不足');
                    }
                }
                if ($coupon) {
                    // 总金额已经计算出来了，检查是否符合优惠券规则
                    $coupon->checkAvailable($user, $totalAmount);
                    // 把订单金额修改为优惠后的金额
                    $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                    // 将订单与优惠券关联
                    $order->couponCode()->associate($coupon);
                    // 增加优惠券的用量，需判断返回值
                    if ($coupon->changeUsed() <= 0) {
                        throw new CouponCodeUnavailableException('该优惠券已被兑完');
                    }
                }
                // 更新订单总金额
                $order->update(['total_amount' => $totalAmount]);

                // 将下单的商品从购物车中移除
                $skuIds = collect($items)->pluck('sku_id')->all();
                app(CartService::class)->remove($skuIds);

                return $order;
            }
        );

        // 这里我们直接使用 dispatch 函数
        dispatch(new CloseOrder($order, config('app.order_ttl')));

        return $order;
    }

    /**
     * 众筹下单
     *
     * @param User        $user
     * @param UserAddress $userAddress
     * @param             $remark
     * @param ProductSku  $sku
     * @param             $amount
     *
     * @return mixed
     * @throws \Throwable
     */
    public function crowdfundingStore(User $user, UserAddress $userAddress, $remark, ProductSku $sku, $amount)
    {
        $order = DB::transaction(
            function () use ($user, $userAddress, $remark, $sku, $amount) {
                // 更新用户地址最后使用时间
                $userAddress->touchLastUsedAt()->save();

                // 创建父订单
                $order = new Order(
                    [
                        'type' => Order::TYPE_CROWDFUNDING,
                        'address' => $this->extractAddress($userAddress),
                        'total_amount' => $sku->price * $amount,
                        'remark' => $remark,
                    ]
                );
                $order->user()->associate($user);
                $order->save();

                // 创建子订单
                /* @var OrderItem $orderItem */
                $orderItem = $order->items()->make(
                    [
                        'amount' => $amount,
                        'price' => $sku->price
                    ]
                );
                $orderItem->product()->associate($sku->product_id);
                $orderItem->productSku()->associate($sku);
                $orderItem->save();

                // 商品库存减少(包括验证)
                if ($sku->decreaseStock($amount) <= 0) {
                    throw new InvalidRequestException("商品库存不足");
                }

                return $order;
            }
        );

        // 定时关闭订单
        $closeDelay = min(config('app.order_ttl'), $sku->product->crowdfunding->end_at->getTimestamp() - time());
        dispatch(new CloseOrder($order, $closeDelay));

        return $order;
    }

    /**
     * 秒杀商品专用下单
     *
     * @param User        $user
     * @param UserAddress $address
     * @param string      $remark
     * @param ProductSku  $sku
     * @param int         $amount
     *
     * @return mixed
     * @throws \Throwable
     */
    public function seckillStore(User $user, UserAddress $address, string $remark, ProductSku $sku, int $amount = 1)
    {
        // 提前扣库存
        $redisResult = app(SeckillService::class)->decrCachedSkuStock($sku->id, 1);
        if ($redisResult === -1) {
            $errmsg = "last check: stock not enough, 秒杀商品sku($sku->id})库存不足";
            \Log::warning($errmsg);
            throw new InvalidRequestException($errmsg, 422);
        }

        \Log::info("预扣除库存成功, 剩余库存: $redisResult");

        try {
            $order = $this->saveSeckillOrder($user, $address, $remark, $sku, $amount);

            // $order = Redis::funnel("seckill_store_funnel:{$sku->id}_")->limit(15)->block(120)->then(
            //     function () use ($user, $address, $remark, $sku, $amount) {
            //         return $this->saveSeckillOrder($user, $address, $remark, $sku, $amount);
            //     }
            // );
        } catch (\Throwable $e) {
            // 出错时还原库存
            app(SeckillService::class)->incrCachedSkuStock($sku->id, 1);
            throw new InternalException("seckill transaction error:" . $e->getMessage(), "system busy");
        }

        // 定时关闭订单
        dispatch(new CloseOrder($order, config('app.seckill_order_ttl')));

        return $order;
    }

    /**
     * 实际创建秒杀订单
     *
     * @param User        $user
     * @param UserAddress $address
     * @param string      $remark
     * @param ProductSku  $sku
     * @param int         $amount
     *
     * @return mixed
     * @throws \Throwable
     */
    protected function saveSeckillOrder(User $user, UserAddress $address, string $remark, ProductSku $sku, int $amount = 1)
    {
        $order = DB::transaction(
            function () use ($user, $address, $remark, $sku, $amount) {
// 扣减库存
                if ($sku->decreaseStock($amount) <= 0) {
                    throw new InvalidRequestException("库存不足");
                }

                // 更新地址使用时间
                $address->touchLastUsedAt();

                // 创建父订单
                $order = new Order(
                    [
                        'type' => Order::TYPE_SECKILL,
                        'address' => $this->extractAddress($address),
                        'total_amount' => $sku->price * $amount,
                        'remark' => $remark,
                    ]
                );
                $order->user()->associate($user);
                $order->save();

                // 创建子订单
                $orderItem = new OrderItem(
                    [
                        'amount' => $amount,
                        'price' => $sku->price,
                    ]
                );
                $orderItem->product()->associate($sku->product_id);
                $orderItem->productSku()->associate($sku);
                $orderItem->order()->associate($order);
                $orderItem->save();

                // 扣减库存
                // if ($sku->decreaseStock($amount) <= 0) {
                //     throw new InvalidRequestException("库存不足");
                // }

                return $order;
            }
        );

        return $order;
    }

    /**
     * @param UserAddress $address
     *
     * @return array
     */
    protected function extractAddress(UserAddress $address)
    {
        // 将地址信息放入订单中
        return [
            'address' => $address->full_address,
            'zip' => $address->zip,
            'contact_name' => $address->contact_name,
            'contact_phone' => $address->contact_phone,
        ];
    }

    /**
     * 订单退款
     *
     * @param Order $order
     *
     * @throws InternalException
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     * @throws \Yansongda\Pay\Exceptions\InvalidConfigException
     * @throws \Yansongda\Pay\Exceptions\InvalidSignException
     */
    public function refundOrder(Order $order)
    {
        if ($order->installment) {
            app(InstallmentService::class)->refund($order->installment);
        } else {
            $this->_refundOrder($order);
        }
    }

    protected function _refundOrder(Order $order)
    {
        // 判断该订单的支付方式
        switch ($order->payment_method) {
            case 'wechat':
                $this->refundWeChat($order);
                break;
            case 'alipay':
                $this->refundAlipay($order);
                break;
            default:
                // 原则上不可能出现，这个只是为了代码健壮性
                throw new InternalException('未知订单支付方式：' . $order->payment_method);
                break;
        }
    }

    /**
     * 退款到微信
     *
     * @param Order $order
     */
    protected function refundWeChat(Order $order)
    {
        // 生成退款订单号
        $refundNo = Order::getAvailableRefundNo();
        app('wechat_pay')->refund(
            [
                'out_trade_no' => $order->no, // 之前的订单流水号
                'total_fee' => $order->total_amount * 100, //原订单金额，单位分
                'refund_fee' => $order->total_amount * 100, // 要退款的订单金额，单位分
                'out_refund_no' => $refundNo, // 退款订单号
                // 微信支付的退款结果并不是实时返回的，而是通过退款回调来通知，因此这里需要配上退款回调接口地址
                'notify_url' => ngrok_route('payment.wechat.refund_notify'),
            ]
        );
        // 将订单状态改成退款中
        $order->update(
            [
                'refund_no' => $refundNo,
                'refund_status' => Order::REFUND_STATUS_PROCESSING,
            ]
        );
    }

    /**
     * 退款到支付宝
     *
     * @param Order $order
     *
     * @throws \Yansongda\Pay\Exceptions\GatewayException
     * @throws \Yansongda\Pay\Exceptions\InvalidConfigException
     * @throws \Yansongda\Pay\Exceptions\InvalidSignException
     */
    protected function refundAlipay(Order $order)
    {
        // 用我们刚刚写的方法来生成一个退款订单号
        $refundNo = Order::getAvailableRefundNo();
        // 调用支付宝支付实例的 refund 方法
        $ret = app('alipay')->refund(
            [
                'out_trade_no' => $order->no, // 之前的订单流水号
                'refund_amount' => $order->total_amount, // 退款金额，单位元
                'out_request_no' => $refundNo, // 退款订单号
            ]
        );
        // 根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
        if ($ret->sub_code) {
            // 将退款失败的保存存入 extra 字段
            $extra = $order->extra;
            $extra['refund_failed_code'] = $ret->sub_code;
            // 将订单的退款状态标记为退款失败
            $order->update(
                [
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_FAILED,
                    'extra' => $extra,
                ]
            );
        } else {
            // 将订单的退款状态标记为退款成功并保存退款订单号
            $order->update(
                [
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_SUCCESS,
                ]
            );
        }
    }
}
