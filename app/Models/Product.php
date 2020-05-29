<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class Product
 * @package App\Models
 *
 * @property int                           $category_id
 * @property string                        $type
 * @property string                        $title
 * @property string                        $description
 * @property string                        $image
 * @property bool                          $on_sale
 * @property float                         $rating
 * @property int                           $sold_count
 * @property int                           $review_count
 * @property float                         $price
 * @property Carbon                        $created_at
 * @property Carbon                        $updated_at
 *
 * @property-read ProductSku[]             $skus
 * @property-read string                   $image_url
 * @property-read Category|null            $category
 * @property-read CrowdfundingProduct|null $crowdfund
 */
class Product extends Model
{
    const TYPE_NORMAL = "normal";
    const TYPE_CROWDFUNDING = "crowdfunding";
    // const TYPE_SECKILL = "seckill";

    private static $typeMap = [
        self::TYPE_NORMAL => '普通商品',
        self::TYPE_CROWDFUNDING => "众筹商品",
        // self::TYPE_SECKILL => "秒杀商品",
    ];

    protected $fillable = [
        'title',
        'description',
        'image',
        'on_sale',
        'rating',
        'sold_count',
        'review_count',
        'price',
        'category_id',
        'type',
    ];

    protected $casts = [
        'on_sale' => 'boolean', // on_sale 是一个布尔类型的字段
    ];

    // 与商品SKU关联
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    public function getImageUrlAttribute()
    {
        // 如果 image 字段本身就已经是完整的 url 就直接返回
        if (Str::startsWith($this->attributes['image'], ['http://', 'https://'])) {
            return $this->attributes['image'];
        }
        return \Storage::disk('public')->url($this->attributes['image']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function crowdfund()
    {
        return $this->hasOne(CrowdfundingProduct::class, 'product_id');
    }
}
