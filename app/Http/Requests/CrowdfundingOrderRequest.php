<?php

namespace App\Http\Requests;

use App\Models\CrowdfundingProduct;
use App\Models\ProductSku;
use Illuminate\Validation\Rule;

/**
 * 众筹商品下单请求
 *
 * Class CrowdfundingOrderRequest
 * @package App\Http\Requests
 */
class CrowdfundingOrderRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 判断用户提交的地址 ID 是否存在于数据库并且属于当前用户
            // 后面这个条件非常重要，否则恶意用户可以用不同的地址 ID 不断提交订单来遍历出平台所有用户的收货地址
            'address_id' => [
                'required',
                Rule::exists('user_addresses', 'id')->where('user_id', $this->user()->id),
            ],
            // 允许一次购买多件同一个sku众筹商品
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            // 商品 sku， 一次仅限购买1种sku
            'sku_id' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    /* @var ProductSku $productSku */
                    if (!$sku = ProductSku::query()->find($value)) {
                        return $fail("商品不存在");
                    }
                    // 商品销售中
                    if (!$sku->product->on_sale) {
                        return $fail("商品未上架");
                    }
                    // 库存足够
                    $amount = $this->input('amount', 0);
                    if ($sku->stock <= 0 || $sku->stock < $amount) {
                        return $fail("商品库存不足");
                    }

                    // 众筹商品类型
                    if (!$sku->product->isCrowdfundProduct()) {
                        return $fail("商品非众筹产品");
                    }

                    //众筹商品的特殊判断
                    /* @var CrowdfundingProduct $crowdfundingProduct */
                    if (!$crowdfundingProduct = CrowdfundingProduct::query()->where('product_id', $sku->product_id)->first()) {
                        return $fail("众筹信息不存在");
                    }
                    if ($crowdfundingProduct->status != CrowdfundingProduct::STATUS_FUNDING) {
                        return $fail("众筹已结束");
                    }
                    if ($crowdfundingProduct->end_at->isPast()) {
                        return $fail("众筹已结束");
                    }
                },
            ],
            // 备注
            'remark' => [
                'nullable',
                'string',
                'max:1000',
            ]
        ];
    }
}
