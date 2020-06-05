<?php

namespace App\Http\Controllers;

use App\Exceptions\InternalException;
use App\Exceptions\InvalidRequestException;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Order;
use App\Models\User;
use App\Services\InstallmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InstallmentsController extends Controller
{
    /**
     * 手续费查询
     *
     * @param Request            $request
     * @param InstallmentService $installmentService
     *
     * @return array
     * @throws InternalException
     * @throws InvalidRequestException
     */
    public function queryFee(Request $request, InstallmentService $installmentService)
    {
        $data = $this->baseCheck($request);
        $order = $data['order'];
        $count = $data['count'];
        $feeRate = $data['feeRate'];

        return $installmentService->getFeeAndAmount($order->total_amount, $count, $feeRate);
    }

    protected function baseCheck(Request $request)
    {
        // 每期的手续费率
        $feeRates = config('app.installment_fee_rate');
        // 逾期费率
        $fineRate = config('app.installment_fine_rate');
        // 订单最低金额限制
        $orderMinAmount = config('app.installment_min_amount');

        // 配置验证
        if (!is_array($feeRates) || is_null($fineRate) || is_null($orderMinAmount)) {
            throw new InternalException("未设置分期配置信息");
        }

        $data = $request->validate(
            [
                'count' => ['required', 'numeric', Rule::in(array_keys($feeRates))],
                'order_id' => ['required', 'numeric']
            ]
        );

        $order = Order::query()->findOrFail($data['order_id']);

        // 权限验证
        $this->authorize('own', $order);

        // 分期数
        $count = $data['count'];
        // 分期费率
        $feeRate = $feeRates[$count];

        // 最低金额限制
        if ($order->total_amount < $orderMinAmount) {
            throw new InvalidRequestException("未达到分期最低金额限制");
        }

        return [
            'count' => $count,
            'order' => $order,
            'feeRate' => $feeRate,
            'fineRate' => $fineRate,
        ];
    }

    /**
     * 创建分期付款记录
     *
     * @param Request            $request
     * @param InstallmentService $installmentService
     *
     * @return Installment
     * @throws InternalException
     * @throws InvalidRequestException
     * @throws \Throwable
     */
    public function store(Request $request, InstallmentService $installmentService)
    {
        $data = $this->baseCheck($request);
        $order = $data['order'];
        $count = $data['count'];
        $feeRate = $data['feeRate'];
        $fineRate = $data['fineRate'];

        try {
            $installment = $installmentService->createInstallment($order, $count, $feeRate, $fineRate);
        } catch (\PDOException $e) {
            throw new InternalException($e->getMessage(), "系统繁忙, 请重试", $e->getCode());
        }
        return $installment;
    }

    public function index(Request $request)
    {
        /* @var User $user */
        $user = $request->user();
        $installments = $user->installments()->orderByDesc('id')->paginate();
        return view(
            'installments.index',
            [
                'installments' => $installments
            ]
        );
    }

    public function show(Installment $installment, Request $request)
    {
        $this->authorize('own', $installment->order);

        return view(
            'installments.show',
            [
                'installment' => $installment
            ]
        );
    }

    /**
     * 支付宝支付某一期分期
     *
     * @param Installment     $installment
     * @param InstallmentItem $item
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws InvalidRequestException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function payByAlipay(Installment $installment, InstallmentItem $item)
    {
        $order = $installment->order;

        // 权限验证
        $this->authorize('own', $order);
        // 订单已关闭
        if ($order->closed) {
            throw new InvalidRequestException("订单已关闭");
        }

        /* @var InstallmentItem $nextItem */
        $nextItem = $installment->items()->whereNull('paid_at')->orderBy('sequence')->firstOrFail();

        // 避免支付错
        if ($item->id != $nextItem->id) {
            throw new InvalidRequestException('页面已过期, 请刷新重试.');
        }

        return app('alipay')->web(
            [
                'out_trade_no' => $nextItem->no,
                'total_amount' => $nextItem->total_amount,
                'subject' => sprintf("分期订单(%d/%d期): %s", $item->sequence, $installment->count, $item->no),
                // 同步回调地址
                'return_url' => route('installments.alipay_return'),
                // 异步回调地址
                'notify_url' => ngrok_route('installments.alipay.notify'),
            ]
        );
    }

    public function alipayReturn(Request $request)
    {
        try {
            app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    /**
     * 支付宝更新
     *
     * @param Request $request
     *
     * @return string|\Symfony\Component\HttpFoundation\Response
     * @throws \Throwable
     * @throws \Yansongda\Pay\Exceptions\InvalidConfigException
     * @throws \Yansongda\Pay\Exceptions\InvalidSignException
     */
    public function alipayNotify(Request $request, InstallmentService $installmentService)
    {
        // 校验输入参数
        $data = app('alipay')->verify();
        // 如果订单状态不是成功或者结束，则不走后续的逻辑
        // 所有交易状态：https://docs.open.alipay.com/59/103672
        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }

        list($installmentNo, $sequence) = explode('_', $data->out_trade_no);
        if (is_null($installmentNo) || is_null($sequence)) {
            Log::error("分期支付(支付宝)回调解析订单号错误", $data->toArray());
            return 'fail';
        }

        /* @var Installment $installment */
        if (!$installment = Installment::query()->where('no', $installmentNo)->first()) {
            Log::error("分期支付(支付宝)回调无法找到对应分期订单", $data->toArray());
            return 'fail';
        }

        /* @var InstallmentItem $installmentItem */
        if (!$installmentItem = $installment->items()->where('sequence', $sequence)->first()) {
            Log::error("分期支付(支付宝)回调无法找到对应分期子订单", $data->toArray());
            return 'fail';
        }

        // 已支付
        if ($installmentItem->paid_at) {
            return app('alipay')->success();
        }

        $paymentNo = $data->trade_no;

        $installmentService->paid($installmentItem, InstallmentItem::PAYMENT_METHOD_ALIPAY, $paymentNo);

        return app('alipay')->success();
    }
}
