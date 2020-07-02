<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class SeckillOrderRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'product_sku_id' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    /**
                     * 检查商品是否存在
                     * @var ProductSku $productSku
                     */
                    if (!$productSku = ProductSku::query()->find($value)) {
                        $fail('商品不存在');
                    }

                    // 是否是秒杀商品
                    $product = $productSku->product;
                    if ($product->type !== Product::TYPE_SECKILL) {
                        $fail('非秒杀商品');
                    }

                    // 商品是否上架
                    if (!$product->on_sale) {
                        $fail('商品未上架');
                    }

                    // 数量是否足够
                    if ($productSku->stock <= 0) {
                        $fail('库存不足');
                    }

                    // 是否在允许时间范围内
                    $seckill = $product->seckill;
                    if ($seckill->is_before_start) {
                        $fail('秒杀未开始');
                    }
                    if ($seckill->is_after_end) {
                        $fail('秒杀已结束');
                    }

                    // 每个秒杀商品, 用户只能参与一次
                    $purchased = Order::query()->where('user_id', $this->user()->id)
                        ->whereHas('items', function (Builder $query) use ($product) {
                            $query->where('product_id', $product->id);
                        })
                        ->where(function (Builder $query) {
                            $query->where('closed', false);
                        })
                        ->exists();
                    if ($purchased) {
                        $fail('秒杀商品每人仅限抢购一次');
                    }
                }
            ],
            'address_id' => [
                'required',
                Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'remark' => [
                'nullable',
                'string',
                'max:1000',
            ]
        ];
    }
}
