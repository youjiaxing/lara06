@extends('layouts.app')
@section('title', $product->title)

@section('content')
    <?php
    /* @var \App\Models\Product $product */
    ?>
	<div class="row">
		<div class="col-lg-10 offset-lg-1">
			<div class="card">
				<div class="card-body product-info">
					<div class="row">
						<div class="col-5">
							<img class="cover" src="{{ $product->image_url }}" alt="">
						</div>
						<div class="col-7">
							<div class="title">{{ $product->title }}</div>

							@if ($product->isCrowdfundProduct())
								@php
									$crowdfund = $product->crowdfunding
								@endphp

								{{--众筹商品信息开始--}}
								<div class="crowdfunding-info">

									<div class="have-text">已筹到</div>
									<div class="total_amount"><span class="symbol">￥</span> {{ $crowdfund->total_amount }}</div>
									{{--这里用到 Bootstrap 进度条组件 --}}
									<div class="progress">
										<div class="progress-bar progress-bar-success progress-bar-striped" role="progressbar"
										     style="min-width:1em; width: {{ min(100, $crowdfund->percent) }}%;"></div>
									</div>
									<div class="progress-info">
										<span class="current_progress">当前进度: {{ $crowdfund->percent }}%</span>
										<span class="float-right user_count">{{ $crowdfund->user_count }}名支持者</span>
									</div>
									{{--如果处于"众筹中", 则输出提示语--}}
									@if ($crowdfund->status == \App\Models\CrowdfundingProduct::STATUS_FUNDING)
										<div>
											此项目必须在 <span class="text-red">{{ $crowdfund->end_at }}</span> 前得到 <span
												class="text-red">￥{{ $crowdfund->target_amount }}</span>
											的支持才可成功, 筹款将在 <span class="text-red">{{ $crowdfund->end_at->diffForHumans() }} 结束!</span>
										</div>
									@endif
								</div>
								{{--众筹商品信息结束--}}
							@else
								{{--普通商品信息开始--}}
								<div class="price"><label>价格</label><em>￥</em><span>{{ $product->price }}</span></div>
								<div class="sales_and_reviews">
									<div class="sold_count">累计销量 <span class="count">{{ $product->sold_count }}</span></div>
									<div class="review_count">累计评价 <span class="count">{{ $product->review_count }}</span></div>
									<div class="rating" title="评分 {{ $product->rating }}">评分 <span
											class="count">{{ str_repeat('★', floor($product->rating)) }}{{ str_repeat('☆', 5 - floor($product->rating)) }}</span>
									</div>
								</div>
								{{--普通商品信息结束--}}
							@endif

							<div class="skus">
								<label>选择</label>
								<div class="btn-group btn-group-toggle" data-toggle="buttons">
									@foreach($product->skus as $sku)
										<label
											class="btn sku-btn"
											data-price="{{ $sku->price }}"
											data-stock="{{ $sku->stock }}"
											data-toggle="tooltip"
											title="{{ $sku->description }}"
											data-placement="bottom">
											<input type="radio" name="skus" autocomplete="off" value="{{ $sku->id }}"> {{ $sku->title }}
										</label>
									@endforeach
								</div>
							</div>
							<div class="cart_amount"><label>数量</label><input type="text" class="form-control form-control-sm" value="1"><span>件</span><span
									class="stock"></span></div>
							<div class="buttons">
								@if (Auth::check())
									@if($favored)
										<button class="btn btn-danger btn-disfavor">取消收藏</button>
									@else
										<button class="btn btn-success btn-favor">❤ 收藏</button>
									@endif

									@if ($product->isCrowdfundProduct())
										@if ($crowdfund->status == \App\Models\CrowdfundingProduct::STATUS_FUNDING)
											<button class="btn btn-primary" id="btn-crowdfunding-commit">参与众筹</button>
										@else
											<button class="btn btn-default" disabled>{{ $crowdfund->status_str }}</button>
										@endif
									@else
										<button class="btn btn-primary btn-add-to-cart">加入购物车</button>
									@endif
								@else
									<a href="{{ route('login') }}" class="btn btn-primary">请先登录</a>
								@endif

							</div>
						</div>
					</div>
					<div class="product-detail">
						<ul class="nav nav-tabs" role="tablist">
							<li class="nav-item">
								<a class="nav-link active" href="#product-detail-tab" aria-controls="product-detail-tab" role="tab" data-toggle="tab"
								   aria-selected="true">商品详情</a>
							</li>
							<li class="nav-item">
								<a class="nav-link" href="#product-reviews-tab" aria-controls="product-reviews-tab" role="tab" data-toggle="tab"
								   aria-selected="false">用户评价</a>
							</li>
						</ul>
						<div class="tab-content">
							<div role="tabpanel" class="tab-pane active" id="product-detail-tab">
								{!! $product->description !!}
							</div>
							<div role="tabpanel" class="tab-pane" id="product-reviews-tab">
								<!-- 评论列表开始 -->
								<table class="table table-bordered table-striped">
									<thead>
									<tr>
										<td>用户</td>
										<td>商品</td>
										<td>评分</td>
										<td>评价</td>
										<td>时间</td>
									</tr>
									</thead>
									<tbody>
									@foreach($reviews as $review)
										<tr>
											<td>{{ $review->order->user->name }}</td>
											<td>{{ $review->productSku->title }}</td>
											<td>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</td>
											<td>{{ $review->review }}</td>
											<td>{{ $review->reviewed_at->format('Y-m-d H:i') }}</td>
										</tr>
									@endforeach
									</tbody>
								</table>
								<!-- 评论列表结束 -->
							</div>
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
            $('[data-toggle="tooltip"]').tooltip({trigger: 'hover'});
            $('.sku-btn').click(function () {
                $('.product-info .price span').text($(this).data('price'));
                $('.product-info .stock').text('库存：' + $(this).data('stock') + '件');
            });

            // 监听收藏按钮的点击事件
            $('.btn-favor').click(function () {
                axios.post('{{ route('products.favor', ['product' => $product->id]) }}')
                    .then(function () {
                        swal('操作成功', '', 'success')
                            .then(function () {  // 这里加了一个 then() 方法
                                location.reload();
                            });
                    }, function (error) {
                        if (error.response && error.response.status === 401) {
                            swal('请先登录', '', 'error');
                        } else if (error.response && error.response.data.msg) {
                            swal(error.response.data.msg, '', 'error');
                        } else {
                            swal('系统错误', '', 'error');
                        }
                    });
            });

            $('.btn-disfavor').click(function () {
                axios.delete('{{ route('products.disfavor', ['product' => $product->id]) }}')
                    .then(function () {
                        swal('操作成功', '', 'success')
                            .then(function () {
                                location.reload();
                            });
                    });
            });

            // 加入购物车按钮点击事件
            $('.btn-add-to-cart').click(function () {

                // 请求加入购物车接口
                axios.post('{{ route('cart.add') }}', {
                    sku_id: $('label.active input[name=skus]').val(),
                    amount: $('.cart_amount input').val(),
                })
                    .then(function () { // 请求成功执行此回调
                        swal('加入购物车成功', '', 'success')
                            .then(function () {
                                location.href = '{{ route('cart.index') }}';
                            });
                    }, function (error) { // 请求失败执行此回调
                        handleAxiosError(e);
                        // if (error.response.status === 401) {
						//
                        //     // http 状态码为 401 代表用户未登陆
                        //     swal('请先登录', '', 'error');
						//
                        // } else if (error.response.status === 422) {
						//
                        //     // http 状态码为 422 代表用户输入校验失败
                        //     var html = '<div>';
                        //     _.each(error.response.data.errors, function (errors) {
                        //         _.each(errors, function (error) {
                        //             html += error + '<br>';
                        //         })
                        //     });
                        //     html += '</div>';
                        //     swal({content: $(html)[0], icon: 'error'})
                        // } else {
						//
                        //     // 其他情况应该是系统挂了
                        //     swal('系统错误', '', 'error');
                        // }
                    })
            });

            $('#btn-crowdfunding-commit').on('click', async function () {
                const sku_id = $('label.active input[name=skus]').val();
                if (!sku_id) {
                    swal("请先选择商品", "", "warning");
                    return;
                }

                $(this).attr('disabled', true);
                try {
                    const addressesUrl = "{{ route('user_addresses.index') }}";
                    // console.log(addressesUrl);
                    const addresses = await axios.get(addressesUrl).then(resp => resp.data);
                    // console.log(addresses);
                    let form = $("<form></form>");

                    // "地址"选择控件
                    let addressField = $('<div class="form-group row">' +
                        '<label class="col-form-label col-md-3">选择地址</label>' +
                        '<div class="col-md-9">' +
                        '<select class="custom-select" name="address_id">' +
                        '</select>' +
                        '</div>' +
                        '</div>');
                    addresses.forEach(function (addr, key) {
                        addressField.find('select').append('<option value="' + addr['id'] + '">' + addr['full_address'] + '</option>');
                    });
                    form.append(addressField)

                    // "购买数量"控件
                    let amountField = $('<div class="form-group row">' +
                        '<label class="col-form-label col-md-3">购买数量</label>' +
                        '<div class="col-md-9">\n' +
                        '<input type="text" class="form-control" name="amount" value="1">' +
                        '</div>' +
                        '</div>');
                    form.append(amountField);

                    // "备注"输入控件
                    let remarkField = $('<div class="form-group row">' +
                        '<label for="remark" class="col-md-3">备注</label>' +
                        '<div class="col-md-9">' +
                        '<textarea class="form-control" name="remark" id="remark" rows="3"></textarea>' +
                        '</div>' +
                        '</div>');
                    form.append(remarkField);

                    console.log(form);

                    // 弹出确认提示框
                    let swalResult = await swal({
                        text: '参与众筹',
                        buttons: ["取消", "参与"],
                        content: form[0],
                    })

                    if (!swalResult) {
                        return;
                    }

                    // 发起众筹订单创建请求
                    const params = {
                        sku_id: sku_id,
                        // amount: $('.cart_amount input').val(),
	                    amount: parseInt(amountField.find('input[name=amount]').val()),
                        address_id: parseInt(addressField.find('select[name=address_id]').val()),
                        remark: form.find('textarea[name=remark]').val()
                    };

                    if (isNaN(params.amount) || params.amount <= 0) {
                        swal("错误", "请正确填写众筹商品数量", "error")
                        return;
                    }

                    console.log(params);

                    const resp = await axios.post("{{ route('crowdfunding_orders.store') }}", params)
                    console.log(resp);

                    window.location.href = '/orders/' + resp.data.id;
                } catch (e) {
	                handleAxiosError(e);
                    // console.log(e);
                    // console.log(e.message);
                    // console.log(e.code);
                    // console.log(e.response);
                    // console.log(e.request);
                } finally {
                    $(this).removeAttr('disabled');
                }
            });

        });

        function handleAxiosError(e) {
            let msg;

            // 服务端响应错误
            if (e.response) {
                const resp = e.response;
                const http_code = resp.status;

                // 未登录
                if (http_code === 401) {
	                msg = "请先登录";
                }
                // 表单验证错误
                else if (http_code === 422 && resp.data.errors) {
                    msg = _.flatten(_.values(e.response.data.errors)).join("\n");
                    console.log(e.response.data.errors,msg);
                }
                // 其他错误
                else if (resp.data.message) {
                    msg = resp.data.message;
                } else {
                    msg = resp.statusText;
                }
            } else {
                // msg = error.message;
                msg = "系统错误";
            }

            swal("错误", msg, "error");
        }

	</script>
@endsection

