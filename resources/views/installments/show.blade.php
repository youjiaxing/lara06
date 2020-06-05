@extends("layouts.app")
@section("title", "分期还款详情")

@section("content")
	@php
		/* @var \App\Models\Installment $installment */
	@endphp
	<div class="card">
		<div class="card-header">
			<h4 class="text-center">分期付款详情</h4>
		</div>
		<div class="card-body">
			<div class="row pl-3 pr-3">
				<div class="col-6">
					<div class="row">
						<div class="col-md-2">商品订单</div>
						<div class="col"><a class="" href="{{ route('orders.show', [$installment->order_id]) }}">点击查看</a>
						</div>
					</div>
					<div class="row">
						<div class="col-md-2">分期金额</div>
						<div class="col">￥ {{ $installment->base_amount }}</div>
					</div>
					<div class="row">
						<div class="col-md-2">分期期限</div>
						<div class="col">{{ $installment->count }} 期</div>
					</div>
					<div class="row">
						<div class="col-md-2">分期费率</div>
						<div class="col">{{ $installment->fee_rate }}%</div>
					</div>
					<div class="row">
						<div class="col-md-2">逾期费率</div>
						<div class="col">{{ $installment->fine_rate }}%</div>
					</div>
					<div class="row">
						<div class="col-md-2">当前状态</div>
						<div class="col">{{ $installment->status_str }}</div>
					</div>
				</div>
				{{--订单未关闭, 且分期未还完--}}
				@if ($installment->status != \App\Models\Installment::STATUS_FINISHED && !$installment->order->closed)
					@php
						/* @var \App\Models\InstallmentItem $nextInstallmentItem */
						$nextInstallmentItem = $installment->items->whereNull('paid_at')->sortBy('sequence')->first();
					@endphp
					<div class="col-6 border-left border-light">
						<div class="row">
							<div class="col-2">近期待还</div>
							<div class="col">￥ {{ $nextInstallmentItem->total_amount }}</div>
						</div>
						<div class="row">
							<div class="col-2">截止日期</div>
							<div class="col">{{ $nextInstallmentItem->due_date->toDateString() }}</div>
						</div>
						<div class="row">
							<div class="offset-2">
								<a class="btn btn-primary btn-sm"
								   href="{{ route('installments.alipay', ['installment' => $installment->id, 'item' => $nextInstallmentItem->id]) }}"
								   role="button">支付宝还款</a>
							</div>
						</div>
					</div>
				@endif
			</div>

			<div class="row mt-5 pl-3 pr-3">
				<table class="table table-hover">
					<thead>
					<tr>
						<th>期数</th>
						<th>还款截止日期</th>
						<th>状态</th>
						<th>订单金额</th>
						<th>手续费</th>
						<th>逾期费</th>
						<th>总计</th>
					</tr>
					</thead>
					<tbody>
					@foreach($installment->items->sortBy('sequence') as $item)
						<tr>
							<td scope="row">{{ $item->sequence }}/{{ $installment->count }} 期</td>
							<td>{{ $item->due_date->toDateString() }}</td>
							<td>
								@if ($item->paid_at)
									<span class="text-success">已还款</span>
								@elseif ($item->due_date->isPast())
									<span class="text-danger">已逾期</span>
								@else
									待付款
								@endif
							</td>
							<td>￥{{ $item->base_amount }}</td>
							<td>￥{{ $item->fee }}</td>
							<td>{{ $item->fine ? "￥". $item->fine : "无" }}</td>
							<td>￥{{ $item->total_amount }}</td>
						</tr>
					@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>

	{{--分期总详情--}}

	{{--每一期分期情况--}}
@stop

@section("scriptsAfterJs")

@stop