<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class Product
 * @package App\Models
 *
 * @property int                                 $category_id
 * @property string                              $type
 * @property string                              $title
 * @property string                              $long_title 长标题
 * @property string                              $description
 * @property string                              $image
 * @property bool                                $on_sale
 * @property float                               $rating
 * @property int                                 $sold_count
 * @property int                                 $review_count
 * @property float                               $price
 * @property Carbon                              $created_at
 * @property Carbon                              $updated_at
 *
 * @property-read ProductSku[]|Collection        $skus
 * @property-read string                         $image_url
 * @property-read Category|null                  $category
 * @property-read CrowdfundingProduct|null       $crowdfunding
 * @property-read ProductProperty[]|Collection   $properties
 * @property-read \Illuminate\Support\Collection $grouped_properties
 * @property-read SeckillProduct|null            $seckill
 */
class Product extends Model
{
    const TYPE_NORMAL = "normal";
    const TYPE_CROWDFUNDING = "crowdfunding";
    const TYPE_SECKILL = "seckill";

    private static $typeMap = [
        self::TYPE_NORMAL => '普通商品',
        self::TYPE_CROWDFUNDING => "众筹商品",
        self::TYPE_SECKILL => "秒杀商品",
    ];

    protected $fillable = [
        'title',
        'long_title',
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
    public function crowdfunding()
    {
        return $this->hasOne(CrowdfundingProduct::class, 'product_id');
    }

    public function isNormalProduct()
    {
        return $this->type === self::TYPE_NORMAL;
    }

    public function isCrowdfundProduct()
    {
        return $this->type === self::TYPE_CROWDFUNDING;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function seckill()
    {
        return $this->hasOne(SeckillProduct::class, 'product_id', 'id');
    }

    public function properties()
    {
        return $this->hasMany(ProductProperty::class, 'product_id', 'id');
    }

    /**
     * @return Collection|\Illuminate\Support\Collection = [
     * '品牌名称' => [
     *      '苹果/Apple',
     * ],
     * '机身颜色' => [
     *      '黑色',
     *      '金色'
     * ],
     * '存储容量' => [
     *      '256G'
     * ]
     * ]
     */
    protected function getGroupedPropertiesAttribute()
    {
        return $this->properties->groupBy('name')->map(
            function (\Illuminate\Support\Collection $properties, $name) {
                return $properties->pluck('value')->all();
            }
        );
    }

    public function toESArray()
    {
        $arr = $this->only(
            [
                'id',
                'type',
                'title',
                'category_id',
                'long_title',
                'on_sale',
                'rating',
                'sold_count',
                'review_count',
                'price',
            ]
        );

        $arr['description'] = strip_tags($this->description);
        $arr['category'] = $this->category ? explode(' - ', $this->category->full_name) : '';
        $arr['category_path'] = $this->category ? $this->category->path : '';
        $arr['skus'] = $this->skus->map(
            function (ProductSku $sku) {
                return $sku->only('title', 'description', 'price');
            }
        );
        $arr['properties'] = $this->properties->map(
            function (ProductProperty $property) {
                $arr = $property->only('name', 'value');
                $arr['search_value'] = $arr['name'] . ':' . $arr['value'];
                return $arr;
            }
        );

        return $arr;
    }

    /**
     * @param Builder $query
     * @param int[]   $productIds
     */
    public function scopeOrderByIds($query, $productIds)
    {
        return $query->orderByRaw('FIND_IN_SET(`id`, ?)', implode(',', $productIds));
    }
}
