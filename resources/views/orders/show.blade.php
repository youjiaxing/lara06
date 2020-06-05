@extends('layouts.app')
@section('title', '查看订单')

@section('content')
    <?php
    /* @var \App\Models\Order $order */
    ?>
	<div class="row">
		<div class="col-lg-10 offset-lg-1">
			<div class="card">
				<div class="card-header">
					<h4>订单详情</h4>
				</div>
				<div class="card-body">
					<table class="table">
						<thead>
						<tr>
							<th>商品信息</th>
							<th class="text-center">单价</th>
							<th class="text-center">数量</th>
							<th class="text-right item-amount">小计</th>
						</tr>
						</thead>
						@foreach($order->items as $index => $item)
							<tr>
								<td class="product-info">
									<div class="preview">
										<a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">
											<img src="{{ $item->product->image_url }}">
										</a>
									</div>
									<div>
              <span class="product-title">
                 <a target="_blank" href="{{ route('products.show', [$item->product_id]) }}">{{ $item->product->title }}</a>
              </span>
										<span class="sku-title">{{ $item->productSku->title }}</span>
									</div>
								</td>
								<td class="sku-price text-center vertical-middle">￥{{ $item->price }}</td>
								<td class="sku-amount text-center vertical-middle">{{ $item->amount }}</td>
								<td class="item-amount text-right vertical-middle">￥{{ number_format($item->price * $item->amount, 2, '.', '') }}</td>
							</tr>
						@endforeach
						<tr>
							<td colspan="4"></td>
						</tr>
					</table>
					<div class="order-bottom">
						<div class="order-info">
							<div class="line">
								<div class="line-label">收货地址：</div>
								<div class="line-value">{{ join(' ', $order->address) }}</div>
							</div>
							<div class="line">
								<div class="line-label">订单备注：</div>
								<div class="line-value">{{ $order->remark ?: '-' }}</div>
							</div>
							<div class="line">
								<div class="line-label">订单编号：</div>
								<div class="line-value">{{ $order->no }}</div>
							</div>
							<!-- 输出物流状态 -->
							<div class="line">
								<div class="line-label">物流状态：</div>
								<div class="line-value">{{ \App\Models\Order::$shipStatusMap[$order->ship_status] }}</div>
							</div>
							<!-- 如果有物流信息则展示 -->
							@if($order->ship_data)
								<div class="line">
									<div class="line-label">物流信息：</div>
									<div class="line-value">{{ $order->ship_data['express_company'] }} {{ $order->ship_data['express_no'] }}</div>
								</div>
							@endif

						<!-- 订单已支付，且退款状态不是未退款时展示退款信息 -->
							@if($order->paid_at && $order->refund_status !== \App\Models\Order::REFUND_STATUS_PENDING)
								<div class="line">
									<div class="line-label">退款状态：</div>
									<div class="line-value">{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}</div>
								</div>
								<div class="line">
									<div class="line-label">退款理由：</div>
									<div class="line-value">{{ $order->extra['refund_reason'] }}</div>
								</div>
							@endif
						</div>
						<div class="order-summary text-right">
							<!-- 展示优惠信息开始 -->
							@if($order->couponCode)
								<div class="text-primary">
									<span>优惠信息：</span>
									<div class="value">{{ $order->couponCode->description }}</div>
								</div>
							@endif
						<!-- 展示优惠信息结束 -->
							<div class="total-amount">
								<span>订单总价：</span>
								<div class="value">￥{{ $order->total_amount }}</div>
							</div>
							<div>
								<span>订单状态：</span>
								<div class="value">
									@if($order->paid_at)
										@if($order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
											已支付
										@else
											{{ \App\Models\Order::$refundStatusMap[$order->refund_status] }}
										@endif
									@elseif($order->closed)
										已关闭
									@else
										未支付
									@endif
								</div>
							</div>
							@if($order->installment)
								<div>
									<span>分期付款：</span>
									<div class="value">
										<a href="{{ route('installments.show', [$order->installment->id]) }}">{{ $order->installment->status_str }}</a>
									</div>
								</div>
							@endif
							@if(isset($order->extra['refund_disagree_reason']))
								<div>
									<span>拒绝退款理由：</span>
									<div class="value">{{ $order->extra['refund_disagree_reason'] }}</div>
								</div>
							@endif
						<!-- 支付按钮开始 -->
							@if(!$order->paid_at && !$order->closed)
								<div class="payment-buttons">
									@if (!$order->installment)
										<a class="btn btn-primary btn-sm" href="{{ route('payment.alipay', ['order' => $order->id]) }}">支付宝支付</a>
										<button class="btn btn-sm btn-success" id='btn-wechat'>微信支付</button>
										@if ($order->total_amount >= config('app.installment_min_amount'))
										<!-- Button trigger modal -->
											<button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#installmentModal">
												分期付款
											</button>

											<!-- Modal -->
											<div class="modal fade" id="installmentModal" tabindex="-1" role="dialog" aria-labelledby="modelTitleId"
											     aria-hidden="true">
												<div class="modal-dialog modal-dialog-centered" role="document">
													<div class="modal-content">
														<div class="modal-header">
															<h5 class="modal-title">分期付款</h5>
															<button type="button" class="close" data-dismiss="modal" aria-label="Close">
																<span aria-hidden="true">&times;</span>
															</button>
														</div>
														<div class="modal-body">
															<table class="table">
																<thead>
																<tr>
																	<th>分期数</th>
																	<th>费率</th>
																	<th></th>
																</tr>
																</thead>
																<tbody>
																@foreach(config('app.installment_fee_rate') as $count => $rate)
																	<tr>
																		<td scope="row">{{ $count }}期</td>
																		<td>{{ $rate }}%</td>
																		<td>
																			<button type="button" class="btn btn-primary btn-installment"
																			        data-count="{{ $count }}">选择
																			</button>
																		</td>
																	</tr>
																@endforeach
																</tbody>
															</table>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
														</div>
													</div>
												</div>
											</div>
										@endif
									@else

									@endif
								</div>
							@endif
						<!-- 支付按钮结束 -->
							<!-- 如果订单的发货状态为已发货则展示确认收货按钮 -->
							@if($order->ship_status === \App\Models\Order::SHIP_STATUS_DELIVERED)
								<div class="receive-button">
									<!-- 将原本的表单替换成下面这个按钮 -->
									<button type="button" id="btn-receive" class="btn btn-sm btn-success">确认收货</button>
								</div>
							@endif

						<!-- 订单已支付，且退款状态是未退款时展示申请退款按钮 -->
							<!-- 众筹商品不支持用户主动申请退款 -->
							@if(!$order->isCrowdfundingOrder() && $order->paid_at && $order->refund_status === \App\Models\Order::REFUND_STATUS_PENDING)
								<div class="refund-button">
									<button class="btn btn-sm btn-danger" id="btn-apply-refund">申请退款</button>
								</div>
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection

@section('scriptsAfterJs')
	<script>
        $(document).ready(function () {
            // 微信支付按钮事件
            $('#btn-wechat').click(function () {
                swal({
                    // content 参数可以是一个 DOM 元素，这里我们用 jQuery 动态生成一个 img 标签，并通过 [0] 的方式获取到 DOM 元素
                    content: $('<img src="{{ route('payment.wechat', ['order' => $order->id]) }}" />')[0],
                    // buttons 参数可以设置按钮显示的文案
                    buttons: ['关闭', '已完成付款'],
                })
                    .then(function (result) {
                        // 如果用户点击了 已完成付款 按钮，则重新加载页面
                        if (result) {
                            location.reload();
                        }
                    })
            });
            // 确认收货按钮点击事件
            $('#btn-receive').click(function () {
                // 弹出确认框
                swal({
                    title: "确认已经收到商品？",
                    icon: "warning",
                    dangerMode: true,
                    buttons: ['取消', '确认收到'],
                })
                    .then(function (ret) {
                        // 如果点击取消按钮则不做任何操作
                        if (!ret) {
                            return;
                        }
                        // ajax 提交确认操作
                        axios.post('{{ route('orders.received', [$order->id]) }}')
                            .then(function () {
                                // 刷新页面
                                location.reload();
                            })
                    });
            });
            // 退款按钮点击事件
            $('#btn-apply-refund').click(function () {
                swal({
                    text: '请输入退款理由',
                    content: "input",
                }).then(function (input) {
                    // 当用户点击 swal 弹出框上的按钮时触发这个函数
                    if (!input) {
                        swal('退款理由不可空', '', 'error');
                        return;
                    }
                    // 请求退款接口
                    axios.post('{{ route('orders.apply_refund', [$order->id]) }}', {reason: input})
                        .then(function () {
                            swal('申请退款成功', '', 'success').then(function () {
                                // 用户点击弹框上按钮时重新加载页面
                                location.reload();
                            });
                        });
                });
            });

            // 分期付款
            $('.btn-installment').on('click', async function () {
                count = $(this).data('count');
                order_id = {{ $order->id }};

                // 确认手续费
                data = await axios.get('{{ route('installments.query_fee') }}' + '?count=' + count + "&order_id=" + order_id)
                    .then(function (response) {
                        return response.data;
                    });
                confirm = await swal({
                    title: "费用明细",
                    text: "分期方案: " + count + " 期\n手续费共: " + data['totalFee'] + " 元(平均每期" + data['avgFee'] + " 元)\n预计每期需还款: " + (data['avgAmount'] + data['avgFee']) + " 元",
                    icon: "warning",
                    buttons: ["取消", "确认"],
                });
                if (!confirm) {
                    return;
                }

                // 设置为分期
                installment = await axios.post('{{ route('installments.store') }}', {
                    count: count,
                    order_id: order_id,
                }).then(response => response.data);
                location.href = "/installments/" + installment.id;
            });
        })
        ;
	</script>
@endsection
