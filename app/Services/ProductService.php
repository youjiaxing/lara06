<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/6/28 23:17
 */

namespace App\Services;

use App\Models\Product;
use App\SearchBuilders\ProductSearchBuilder;

class ProductService
{
    /**
     * @param Product $product
     * @param int     $count
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSimilarProducts(Product $product, $count = 4)
    {
        if (count($product->properties) === 0) {
            return collect([]);
        }

        // 查找相似商品
        $builder = new ProductSearchBuilder();
        $builder
            ->paginate($count,1)
            ->onSale();
        foreach ($product->properties as $property) {
            $builder->propertyFilter($property->name, $property->value, 'should');
        }
        $propertyCount = count($product->properties);
        $builder->minShouldMatch(ceil($propertyCount / 2)); // 需有一半以上属性相似
        // 排除当前商品
        $builder->excludeProducts($product->id);
        $searchResult = app('es')->search($builder->build());
        $similarProductIds  = collect($searchResult['hits']['hits'])->pluck('_source.id')->toArray();
        return Product::query()->whereIn('id', $similarProductIds )->orderByIds($similarProductIds )->get();
    }
}