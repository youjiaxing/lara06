@if (isset($category))
	@if (isset($category['children']) && !empty($category['children']))
		<li class="dropdown-submenu">
			<a href="{{ route('products.index', ['category_id' => $category['id']]) }}" class="dropdown-item dropdown-toggle" data-toggle="dropdown">
				{{ $category['name'] }}
			</a>
			<ul class="dropdown-menu">
				{{--遍历子类目, 递归调用模板--}}
				@each('layouts._category_item', $category['children'], 'category')
			</ul>
		</li>
	@else
		<li>
			<a href="{{ route('products.index', ['category_id' => $category['id']]) }}" class="dropdown-item">{{ $category['name'] }}</a>
		</li>
    @endif
@endif