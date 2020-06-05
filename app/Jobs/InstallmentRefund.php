<?php

namespace App\Jobs;

use App\Exceptions\InternalException;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Order;
use App\Services\InstallmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InstallmentRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $installment;

    /**
     * InstallmentRefund constructor.
     *
     * @param Installment $installment
     */
    public function __construct(Installment $installment)
    {
        $this->installment = $installment;
    }


    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(InstallmentService $service)
    {
        $installment = $this->installment;
        $order = $installment->order;
        if ($order->refund_status != Order::REFUND_STATUS_PROCESSING) {
            return;
        }

        // 对所有子分期项逐一退款
        foreach ($installment->items as $item) {
            if (!$item->paid_at || $item->refund_status == InstallmentItem::REFUND_STATUS_SUCCESS) {
                continue;
            }

            try {
                switch ($item->payment_method) {
                    case InstallmentItem::PAYMENT_METHOD_ALIPAY:
                        $service->refundAlipay($order, $item);
                        break;

                    case InstallmentItem::PAYMENT_METHOD_WECHAT:
                        $service->refundWeChat($order, $item);
                        break;

                    default:
                        throw new InternalException("未知的支付渠道: " . $item->payment_method);
                }
            } catch (\Throwable $e) {
                \Log::warning(sprintf("分期退款(%s)失败: %s", $item->no, $e->getMessage()));
                continue;
            }
        }
        Log::debug(sprintf("已对分期订单(%s)子分期发起退款", $installment->no));

        $service->checkRefunding($installment);
    }
}
