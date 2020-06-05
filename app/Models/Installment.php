<?php

namespace App\Models;

use App\Exceptions\InternalException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * 分期信息表
 *
 * Class Installments
 * @package App\Models
 *
 * @property int                               $id
 * @property string                            $no           分期流水号
 * @property int                               $order_id
 * @property int                               $user_id
 * @property float                             $base_amount  订单原始金额
 * @property int                               $count        分期数
 * @property float                             $fee_rate     分期费率
 * @property float                             $fine_rate    逾期费率
 * @property string                            $status       分期状态
 *
 * @property-read string                       $status_str   分期状态说明
 * @property-read User                         $user
 * @property-read Order                        $order
 * @property-read InstallmentItem[]|Collection $items
 */
class Installment extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_REPAYING = 'repaying';
    const STATUS_FINISHED = 'finished';

    protected static $statusMap = [
        self::STATUS_PENDING => '未执行',
        self::STATUS_REPAYING => '还款中',
        self::STATUS_FINISHED => '已完成',
    ];

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function (Installment $installment) {
                if ($installment->no) {
                    return;
                }

                $installment->no = static::generateNo();
            }
        );
    }

    /**
     * 生成唯一分期流水号
     *
     * @return string
     * @throws InternalException
     */
    public static function generateNo()
    {
        for ($i = 0; $i < 10; $i++) {
            $noPrefix = date('YmdHis');
            $no = $noPrefix . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
        }

        throw new InternalException("无法生成唯一分期流水号");
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(InstallmentItem::class, 'installment_id', 'id');
    }

    protected function getStatusStrAttribute()
    {
        return static::$statusMap[$this->attributes['status']];
    }
}
