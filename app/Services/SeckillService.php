<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/7/3 14:15
 */

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSku;

class SeckillService
{
    /**
     * @param int $skuId
     *
     * @return string
     */
    protected function getSkuCacheKey($skuId)
    {
        return "seckill_sku_stock:" . $skuId;
    }

    /**
     * 将 sku 的库存缓存供秒杀优化性能
     *
     * @param Product $product
     */
    public function cacheStock(Product $product)
    {
        $timeDiff = $product->seckill->end_at->diffInSeconds();
        /* @var \Redis $redis */
        $redis = app('redis');

        $product->skus->each(
            function (ProductSku $sku) use ($product, $timeDiff, $redis) {
                $cacheKey = $this->getSkuCacheKey($sku->id);
                if ($product->on_sale && $timeDiff > 0) {
                    $redis->setex($cacheKey, $timeDiff, $sku->stock);
                } else {
                    $redis->del($cacheKey);
                }
            }
        );
    }

    /**
     * @param $skuId
     *
     * @return int 返回缓存中 sku 的库存量
     */
    public function getCachedSkuStock($skuId)
    {
        $cacheKey = $this->getSkuCacheKey($skuId);
        /* @var \Redis $redis */
        $redis = app('redis');

        $stock = (int)$redis->get($cacheKey);
        return $stock;
    }

    /**
     * 扣除缓存中 sku 的库存
     *
     * @param     $skuId
     * @param int $decr
     *
     * @return int -1 表示库存不足, 否则返回扣除后的剩余库存量
     */
    public function decrCachedSkuStock($skuId, $decr = 1)
    {
        $cacheKey = $this->getSkuCacheKey($skuId);
        /* @var \Redis $redis */
        $redis = app('redis');

        $lua = <<<EOF
-- KEYS[1] string   存放库存的key
-- ARGV[1] int      扣减库存数
local key = KEYS[1]
local decr = ARGV[1]
local stock = redis.call("get", key)

if stock and stock >= decr then
    return redis.call("decrby", decr)
else
    return -1;
end
EOF;
        return $redis->eval($lua, [$skuId, $decr], 1);
    }

    /**
     *
     * @param     $skuId
     * @param int $incr
     *
     * @return int 返回最新库存
     */
    public function incrCachedSkuStock($skuId, $incr = 1)
    {
        $cacheKey = $this->getSkuCacheKey($skuId);
        /* @var \Redis $redis */
        $redis = app('redis');

        if ($redis->exists($cacheKey)) {
            return $redis->incrBy($cacheKey, $incr);
        }
        return 0;
    }
}