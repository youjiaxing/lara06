<?php

namespace App\Console\Commands\Cron;

use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Order;
use App\Services\InstallmentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class UpdateInstallmentFineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:update-installment-fine';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每日更新分期订单中的罚金';

    /**
     * @var InstallmentService
     */
    protected $installmentService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(InstallmentService $installmentService)
    {
        parent::__construct();

        $this->installmentService = $installmentService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // TODO 也可以查询出所有过期的 分期子表(installment_items), 再逐个处理.



        // 找出所有分期付款中, 逾期的分期
        // 订单: 未关闭, 未退款成功
        Installment::query()
            ->where('status', Installment::STATUS_REPAYING)
            ->whereHas(
                'order',
                function (Builder $builder) {
                    $builder->where('closed', false)->where('refund_status', '!=', Order::REFUND_STATUS_SUCCESS);
                }
            )->whereHas(
                'items',
                function (Builder $builder) {
                    $builder->whereNull('paid_at')->where('due_date', '<', now());
                }
            )->chunkById(1000, function (Collection $installments) {
                foreach ($installments as $installment) {
                    $this->updateFine($installment);
                }
            });
    }

    protected function updateFine(Installment $installment)
    {
        foreach ($installment->items as $item) {
            if (!$item->isOverdue()) {
                continue;
            }

            $oldFine = $item->fine ?? 0;
            $fine = $this->installmentService->calcFine($installment, $item);
            if ($fine <= 0 || $fine == $oldFine) {
                continue;
            }

            $item->update(['fine' => $fine]);
            Log::debug(sprintf("更新分期子订单(%s)逾期费: %.2f -> %.2f", $item->id, $oldFine, $fine));
        }
    }
}
