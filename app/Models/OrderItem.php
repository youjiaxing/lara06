<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class OrderItem
 * @package App\Models
 *
 * @property-read Product    $product
 * @property-read ProductSku $productSku
 * @property-read Order      $order
 */
class OrderItem extends Model
{
    protected $fillable = ['amount', 'price', 'rating', 'review', 'reviewed_at', 'product_id', 'product_sku_id', 'order_id'];
    protected $dates = ['reviewed_at'];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productSku()
    {
        return $this->belongsTo(ProductSku::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
