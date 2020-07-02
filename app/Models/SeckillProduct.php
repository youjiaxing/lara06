<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SeckillProduct
 * @package App\Models
 *
 * @property Carbon       $start_at
 * @property Carbon       $end_at
 *
 * @property-read Product $product
 * @property-read bool    $is_before_start
 * @property-read bool    $is_after_end
 */
class SeckillProduct extends Model
{
    protected $dates = [
        'start_at',
        'end_at'
    ];

    protected $guarded = [];

    protected $appends = [
        'is_before_start',
        'is_after_end',
    ];

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function getIsBeforeStartAttribute()
    {
        return $this->start_at->isFuture();
    }

    public function getIsAfterEndAttribute()
    {
        return $this->end_at->isPast();
    }
}
