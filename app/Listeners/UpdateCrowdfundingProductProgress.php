<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\CrowdfundingProduct;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 更新众筹商品进度
 *
 * Class UpdateCrowdfundingProductProgress
 * @package App\Listeners
 */
class UpdateCrowdfundingProductProgress implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param OrderPaid $event
     *
     * @return void
     */
    public function handle(OrderPaid $event)
    {
        $order = $event->getOrder();
        if (!$order->isCrowdfundingOrder()) {
            return;
        }

        /* @var CrowdfundingProduct $crowdfundingProduct */
        $crowdfundingProduct = $order->items[0]->product->crowdfunding;
        if (!$crowdfundingProduct) {
            throw new \Exception(sprintf("子订单(%d)非众筹订单", $order->items[0]->id));
        }

        // 当前金额更新
        // 参与人数更新
        $data = OrderItem::query()->where('product_id', $crowdfundingProduct->product_id)
            ->whereHas(
                'order',
                function (Builder $builder) {
                    $builder->whereNotNull('paid_at');
                }
            )->first(
                [
                    DB::raw('sum(amount * price) as total_amount'),
                    DB::raw('count(distinct(user_id)) as user_count')
                ]
            );

        $crowdfundingProduct->update(
            [
                'target_amount' => $data['total_amount'],
                'user_count' => $data['user_count'],
            ]
        );

        Log::info(sprintf("众筹商品更新数据. 当前已筹集金额: %.2f, 总参与人数: %d, 总进度: %.2f%%", $crowdfundingProduct->target_amount, $crowdfundingProduct->user_count, $crowdfundingProduct->percent));
    }
}
