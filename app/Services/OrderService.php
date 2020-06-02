<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Models\CouponCode;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\ProductSku;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
}
