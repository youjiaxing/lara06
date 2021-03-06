<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\SearchBuilders\ProductSearchBuilder;
use App\Services\ProductService;
use Elasticsearch\Client;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ProductsController extends Controller
{
    public function index(Request $request, Client $es)
    {
        // 分页
        $pageSize = 16;
        $page = (int)$request->input('page', 1);

        // 构建 ES 查询参数
        $builder = new ProductSearchBuilder();
        $builder->paginate($pageSize, $page)
            ->onSale();

        // 多字段搜索
        if ($search = $request->input('search', '')) {
            // 对于空格隔开的搜索, 视为需同时满足的多个条件
            $words = array_filter(explode(' ', $search));
            $builder->keywords($words);
        }

        // 商品类别筛选
        $categoryId = (int)$request->input('category_id');
        if ($categoryId && $category = Category::query()->find($categoryId)) {
            $builder->category($category);
        }

        // 从用户请求参数获取 filters
        $propertyFilters = [];
        if ($filterString = $request->input('filters')) {
            $filterArray = explode('|', $filterString);
            foreach ($filterArray as $filter) {
                list($name, $value) = explode(':', $filter);
                $builder->propertyFilter($name, $value);
                $propertyFilters[$name] = $value;

            }
        }

        // 仅在用户输入搜索词或使用类目筛选时才会做聚合
        if ($search || isset($category)) {
            $builder->propertyAggregate();
        }

        // 排序
        if ($order = $request->input('order', '')) {
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        $esStart = microtime(true);
        $result = $es->search($builder->build());
        $esEnd = microtime(true);
        // dump(json_encode($builder->build()['body']));
        // dump($params, $result);

        // 获取搜索结果的所有商品 id
        $productIds = Arr::pluck($result['hits']['hits'], '_source.id');
        // 读取商品数据(需按照 ES 结果中的顺序来)
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderByIds($productIds)
            ->get();

        // 构建 Paginator 对象
        $products = new LengthAwarePaginator(
            $products,
            $result['hits']['total']['value'],
            $pageSize,
            $page,
            [
                'path' => route('products.index', false)
            ]
        );

        // 使用聚合结果支持 "分面搜索"
        $properties = [];
        if (isset($result['aggregations'])) {
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
                ->map(
                    function ($bucket) {
                        return [
                            'key' => $bucket['key'],
                            'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                        ];
                    }
                )
                ->filter(
                    function ($bucket) use ($propertyFilters) {
                        return !array_key_exists($bucket['key'], $propertyFilters) && count($bucket['values']) > 1;
                    }
                )
                ->toArray();
        }

        return view(
            'products.index',
            [
                'products' => $products,
                'category' => $category ?? null,
                'filters' => [
                    'search' => $search,
                    'order' => $order,
                    'category' => $categoryId,
                ],
                'properties' => $properties,
                'propertyFilters' => $propertyFilters,
            ]
        );
    }

    public function index_from_db(Request $request)
    {
        // 创建一个查询构造器
        $builder = Product::query()->where('on_sale', true);

        // 判断是否有提交 search 参数，如果有就赋值给 $search 变量
        // search 参数用来模糊搜索商品
        if ($search = $request->input('search', '')) {
            $like = '%' . $search . '%';
            // 模糊搜索商品标题、商品详情、SKU 标题、SKU描述
            $builder->where(
                function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas(
                            'skus',
                            function ($query) use ($like) {
                                $query->where('title', 'like', $like)
                                    ->orWhere('description', 'like', $like);
                            }
                        );
                }
            );
        }

        // 判断分类
        $categoryId = (int)$request->input('category_id');
        if ($categoryId && $category = Category::query()->find($categoryId)) {
            /* @var Category $category */
            if ($category->is_directory) {
                $builder->whereIn(
                    'category_id',
                    Category::query()->where('path', 'like', $category->path . $category->id . Category::PATH_DELIMITER)->select('id')
                );
                // $builder->whereHas('category', function (Builder $builder) use ($category) {
                //     $builder->where('path', 'like', $category->path . $category->id . Category::PATH_DELIMITER);
                // });
            } else {
                $builder->where('category_id', $category->id);
            }
        }

        // 是否有提交 order 参数，如果有就赋值给 $order 变量
        // order 参数用来控制商品的排序规则
        if ($order = $request->input('order', '')) {
            // 是否是以 _asc 或者 _desc 结尾
            if (preg_match('/^(.+)_(asc|desc)$/', $order, $m)) {
                // 如果字符串的开头是这 3 个字符串之一，说明是一个合法的排序值
                if (in_array($m[1], ['price', 'sold_count', 'rating'])) {
                    // 根据传入的排序值来构造排序参数
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        $products = $builder->paginate(16);

        return view(
            'products.index',
            [
                'products' => $products,
                'category' => $category ?? null,
                'filters' => [
                    'search' => $search,
                    'order' => $order,
                    'category' => $categoryId,
                ],
            ]
        );
    }

    public function show(Product $product, Request $request, ProductService $productService)
    {
        // 判断商品是否已经上架，如果没有上架则抛出异常。
        if (!$product->on_sale) {
            throw new InvalidRequestException('商品未上架');
        }

        $favored = false;
        // 用户未登录时返回的是 null，已登录时返回的是对应的用户对象
        if ($user = $request->user()) {
            // 从当前用户已收藏的商品中搜索 id 为当前商品 id 的商品
            // boolval() 函数用于把值转为布尔值
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku']) // 预先加载关联关系
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at') // 筛选出已评价的
            ->orderBy('reviewed_at', 'desc') // 按评价时间倒序
            ->limit(10) // 取出 10 条
            ->get();

        // 查找相似商品
        $similarProducts = $productService->getSimilarProducts($product);

        // 最后别忘了注入到模板中
        return view(
            'products.show',
            [
                'product' => $product,
                'favored' => $favored,
                'reviews' => $reviews,
                'similar' => $similarProducts,
            ]
        );
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', ['products' => $products]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if ($user->favoriteProducts()->find($product->id)) {
            return [];
        }

        $user->favoriteProducts()->attach($product);

        return [];
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }
}
