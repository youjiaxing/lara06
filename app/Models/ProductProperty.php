<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductProperty
 * @package App\Models
 *
 * @property string  $name
 * @property string  $value
 * @property int     $product_id
 *
 * @property Product $product
 */
class ProductProperty extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
