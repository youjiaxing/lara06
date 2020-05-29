@extends('layouts.app')
@section('title', '商品列表')

@section('content')
	<div class="row">
		<div class="col-lg-10 offset-lg-1">
			<div class="card">
				<div class="card-body">
					<!-- 筛选组件开始 -->
					<form action="{{ route('products.index') }}" class="search-form">
						<div class="form-row">
							<div class="col-md-9">
								<div class="form-row">
									<!-- 类目面包屑开始 -->
									<div class="col-auto category-breadcrumb">
										{{--添加一个名为全部的链接, 直接跳转到商品列表页--}}
										<a href="{{ route('products.index') }}" class="all-products">全部</a> &gt;
										{{--如果当前通过类目筛选--}}
                                        <?php
                                        /* @var \App\Models\Category $category */
                                        ?>
										@if ($category)
											{{--遍历类目的所有祖先节点--}}
											@foreach($category->ancestors as $ancestor)
												<span class="category">
													<a href="{{ route('products.index', ['category_id' => $ancestor->id]) }}">{{ $ancestor->name }}</a>
													<span>&gt;</span>
												</span>
											@endforeach
											{{--最后展现出当前类目的名称--}}
											<span class="category">{{ $category->name }}</span>
											{{--表单需保持当前类目信息--}}
											<input type="hidden" name="category_id" value="{{ $category->id }}">
										@endif
									</div>
									<!-- 类目面包屑结束 -->

									<div class="col-auto"><input type="text" class="form-control form-control-sm" name="search" placeholder="搜索"></div>
									<div class="col-auto">
										<button class="btn btn-primary btn-sm">搜索</button>
									</div>
								</div>
							</div>
							<div class="col-md-3">
								<select name="order" class="form-control form-control-sm float-right">
									<option value="">排序方式</option>
									<option value="price_asc">价格从低到高</option>
									<option value="price_desc">价格从高到低</option>
									<option value="sold_count_desc">销量从高到低</option>
									<option value="sold_count_asc">销量从低到高</option>
									<option value="rating_desc">评价从高到低</option>
									<option value="rating_asc">评价从低到高</option>
								</select>
							</div>
						</div>
					</form>
					<!-- 筛选组件结束 -->

					<!--展示子类目开始-->
					<div class="filters">
						{{--如果当前是通过类目筛选, 并且此类目是一个父类目--}}
						@if ($category && $category->is_directory)
							<div class="row">
								<div class="col-3 filter-key">子类目:</div>
								<div class="col-9 filter-values">
									<!--遍历直接子类目-->
									@foreach($category->children as $child)
										<a href="{{ route('products.index', ['category_id' => $child->id]) }}">{{ $child->name }}</a>
									@endforeach
								</div>
							</div>
						@endif
					</div>
					<!--展示子类目结束-->

					<div class="row products-list">
						@foreach($products as $product)
							<div class="col-3 product-item">
								<div class="product-content">
									<div class="top">
										<div class="img">
											<a href="{{ route('products.show', ['product' => $product->id]) }}">
												<img src="{{ $product->image_url }}" alt="">
											</a>
										</div>
										<div class="price"><b>￥</b>{{ $product->price }}</div>
										<div class="title">
											<a href="{{ route('products.show', ['product' => $product->id]) }}">{{ $product->title }}</a>
										</div>
									</div>
								</div>
							</div>
						@endforeach
					</div>
					<div class="float-right">{{ $products->appends($filters)->render() }}</div>
				</div>
			</div>
		</div>
	</div>
@endsection
@section('scriptsAfterJs')
	<script>
        var filters = {!! json_encode($filters) !!};
        $(document).ready(function () {
            $('.search-form input[name=search]').val(filters.search);
            $('.search-form select[name=order]').val(filters.order);
            $('.search-form select[name=order]').on('change', function () {
                $('.search-form').submit();
            });
        })
	</script>
@endsection
