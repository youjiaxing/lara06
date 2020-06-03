<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrowdfundingProduct
 * @package App\Models
 * @property int          $product_id
 * @property float        $total_amount
 * @property float        $target_amount
 * @property Carbon       $end_at
 * @property int          $user_count
 * @property string       $status
 *
 * @property-read float   $percent 进度百分数(保留2位小数), eg. 45.21 表示完成 45.21%
 * @property-read string  $status_str
 * @property-read Product $product
 */
class CrowdfundingProduct extends Model
{
    const STATUS_FUNDING = 'funding';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';

    private static $statusMap = [
        self::STATUS_FUNDING => "众筹中",
        self::STATUS_SUCCESS => "众筹成功",
        self::STATUS_FAIL => "众筹失败"
    ];

    protected $fillable = [
        'product_id',
        'total_amount',
        'target_amount',
        'user_count',
        'end_at',
        'status',
    ];

    protected $appends = [
        'status_str',
        'percent',
    ];

    protected $dates = ['end_at'];

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getPercentAttribute()
    {
        return (float)number_format(
            100 * $this->attributes['total_amount'] / max(1, $this->attributes['target_amount']),
            2,
            '.',
            ''
        );
    }

    public function getStatusStrAttribute()
    {
        return static::$statusMap[$this->status];
    }
}
