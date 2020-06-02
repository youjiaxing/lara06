<?php

namespace App\Console\Commands\Cron;

use App\Jobs\RefundCrowdfundingOrder;
use App\Models\CrowdfundingProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\CrowdfundingFinishNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FinishCrowdfundingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:finish-crowdfunding';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '结束众筹';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /*
         * 遍历所有过期的众筹商品
         *  |- 成功
         *  |- 失败
         */
        CrowdfundingProduct::query()
            ->where('status', CrowdfundingProduct::STATUS_FUNDING)
            ->where('end_at', '<=', Carbon::now())
            ->get()
            ->each(function ($v, $k) {
                $this->handleCrowdfundingProduct($v);
            });
    }

    protected function handleCrowdfundingProduct(CrowdfundingProduct $crowdfunding)
    {
        if ($crowdfunding->status != CrowdfundingProduct::STATUS_FUNDING) {
            return;
        }

        if ($crowdfunding->total_amount >= $crowdfunding->target_amount) {
            $this->handleSuccessCrowdfunding($crowdfunding);
        } else {
            $this->handleFailCrowdfunding($crowdfunding);
        }
    }

    /**
     * 处理众筹成功的商品
     *
     * @param CrowdfundingProduct $crowdfunding
     */
    protected function handleSuccessCrowdfunding(CrowdfundingProduct $crowdfunding)
    {
        $crowdfunding->update(['status' => CrowdfundingProduct::STATUS_SUCCESS]);

        // 发送通知邮件
        $this->getCrowdfundingOrders($crowdfunding)
            ->each(function (Order $order) {
                /* @var User $user */
                $user = $order->user;
                $user->notify(new CrowdfundingFinishNotification($order));
            });
    }

    /**
     * 处理众筹失败的商品
     *
     * @param CrowdfundingProduct $crowdfunding
     */
    protected function handleFailCrowdfunding(CrowdfundingProduct $crowdfunding)
    {
        $crowdfunding->update(['status' => CrowdfundingProduct::STATUS_FAIL]);

        $this->getCrowdfundingOrders($crowdfunding)
            ->each(function (Order $order) {
                // 发送通知邮件
                /* @var User $user */
                $user = $order->user;
                $user->notify(new CrowdfundingFinishNotification($order));

                // 逐个退款
                dispatch(new RefundCrowdfundingOrder($order));
            });
    }

    /**
     * @param CrowdfundingProduct $crowdfundingProduct
     *
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    protected function getCrowdfundingOrders(CrowdfundingProduct $crowdfundingProduct)
    {
        return Order::query()
            ->where('type', Order::TYPE_CROWDFUNDING)
            ->whereNotNull('paid_at')
            ->whereHas('items', function (Builder $builder) use ($crowdfundingProduct) {
                $builder->where('product_id', $crowdfundingProduct->product_id);
            });
    }
}
