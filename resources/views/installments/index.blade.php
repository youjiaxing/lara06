@extends("layouts.app")
@section("title", "分期付款列表")

@section("content")
	@php
		/* @var Illuminate\Contracts\Pagination\Paginator $installments */
	@endphp
	<div class="card">
		<div class="card-header">
			<h4 class="text-center">分期付款列表</h4>
		</div>
		<div class="card-body">
			<table class="table table-hover table-striped">
				<thead>
				<tr>
					<th>编号</th>
					<th>金额</th>
					<th>期数</th>
					<th>费率</th>
					<th>状态</th>
					<th>日期</th>
					<th>操作</th>
				</tr>
				</thead>
				<tbody>
				@foreach($installments as $installment)
					@php
						/* @var \App\Models\Installment $installment*/
					@endphp
					<tr>
						<td scope="row">{{ $installment->no }}</td>
						<td>￥ {{ $installment->base_amount }}</td>
						<td>{{ $installment->count }}</td>
						<td>{{ $installment->fee_rate/100 }} %</td>
						<td>{{ $installment->status_str }}</td>
						<td>{{ $installment->created_at }}</td>
						<td><a class="btn btn-primary btn-sm" href="{{ route('installments.show', [$installment]) }}" role="button">查看</a></td>
					</tr>
				@endforeach
				</tbody>
			</table>
		</div>

		@if ($installments->hasMorePages())
		<div class="card-footer">
			{!! $installments->render() !!}
		</div>
		@endif
	</div>
@stop

@section("scriptsAfterJs")
@stop