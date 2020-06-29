@extends('layouts.app')
@section('title', '商品列表')

@section('content')
	<div class="row">
		<div class="col-lg-10 offset-lg-1">
			<div class="card">
				<div class="card-body">
					<!-- 筛选组件开始 -->
					<form action="{{ route('products.index') }}" class="search-form">
						<input type="hidden" name="filters" value="{{ collect($propertyFilters)->map(function($value, $key) {  return $key . ":". $value;})->implode('|') }}">

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

										{{--商品属性面包屑开始--}}
										<!-- 遍历当前属性筛选条件 -->
										@foreach($propertyFilters as $name => $value)
											<span class="filter">
												{{ $name }}: <span class="filter-value">{{ $value }}</span>
												<a class="remove-filter" data-key="{{ $name }}" href="#">x</a>
											</span>
										@endforeach
										{{--商品属性面包屑结束--}}

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

						{{--分面搜索结果开始--}}
						@foreach($properties as $property)
							<div class="row faceted-search">
								<div class="col-3 filter-key">{{ $property['key'] }}</div>
								<div class="col-9 filter-values">
									@foreach($property['values'] as $value)
										<a href="#" data-key="{{ $property['key'] }}" data-value="{{ $value }}">{{ $value }}</a>
									@endforeach
								</div>
							</div>
						@endforeach
						{{--分面搜索结果结束--}}

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

	        $('.faceted-search .filter-values').on('click', 'a', function (e) {
		        e.preventDefault();

	            let key = this.dataset.key;
	            let value = this.dataset.value;
                appendFilterToQuery(key, value);
	        });

	        $('.remove-filter').on('click', function (e) {
		        e.preventDefault();

		        let key = this.dataset.key;
                removeFilterFromQuery(key);
            })
        })

        function parseSearch() {
            let searches = {};
            let params = (new URL(document.location)).searchParams;
            for (const [key, value] of params) {
                // console.info(key,value);
                searches[key] = value;
            }
            return searches;
        }

        function buildSearch(searches) {
            let query = '?';
            _.forEach(searches, function (value, key) {
                // console.log(`${key} = ${value}`)
                query += key + '=' + value + '&';
                // query += encodeURIComponent(key) + '=' + encodeURIComponent(value) + '&';
            });
            return query.substr(0, query.length - 1);
        }

        // 将新的 filter 追加到当前的 Url 中
        function appendFilterToQuery(name, value) {
            // 解析当前 Url 的查询参数
            var searches = parseSearch();
            // 如果已经有了 filters 查询
            if (searches['filters']) {
                // 则在已有的 filters 后追加
                searches['filters'] += '|' + name + ':' + value;
            } else {
                // 否则初始化 filters
                searches['filters'] = name + ':' + value;
            }
            // 重新构建查询参数，并触发浏览器跳转
            location.search = buildSearch(searches);
        }

        function removeFilterFromQuery(name) {
            let searches = parseSearch();
            if (!searches['filters']) {
                return;
            }
            let filters = searches['filters'].split('|').filter(function (filter) {
                let [key, value] = filter.split(':');
                return key !== name;
            })
            searches['filters'] = filters;
	        console.log(filters);
	        location.search = buildSearch(searches);
        }
	</script>
@endsection
