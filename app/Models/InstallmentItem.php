<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * 分期还款子表
 *
 * Class InstallmentItems
 * @package App\Models
 *
 * @property int              $installment_id
 * @property int              $sequence          分期还款序号
 * @property float            $base_amount       当期还款订单金额
 * @property float            $fee               当期还款手续费
 * @property float            $fine              当期还款逾期费, 在还款前该值为0
 * @property Carbon|null      $due_date          当期还款截至时间
 * @property Carbon|null      $paid_at           当期实际还款时间
 * @property string           $payment_method    还款方式
 * @property string           $payment_no        第三方支付单号
 * @property string           $refund_status     还款状态
 *
 * @property-read string      $refund_status_str 还款状态说明
 * @property-read Installment $installment
 * @property-read float       $total_mount       本期应还总费用
 */
class InstallmentItem extends Model
{
    const REFUND_STATUS_PENDING = 'pending';
    const REFUND_STATUS_REFUNDING = 'refunding';
    const REFUND_STATUS_SUCCESS = 'success';
    const REFUND_STATUS_FAILED = 'failed';

    protected static $refundStatusMap = [
        self::REFUND_STATUS_PENDING => '未发生退款',
        self::REFUND_STATUS_REFUNDING => '退款中',
        self::REFUND_STATUS_SUCCESS => '退款成功',
        self::REFUND_STATUS_FAILED => '退款失败',
    ];

    protected $guarded = [];

    protected $dates = [
        'due_date',
        'paid_at',
    ];

    protected function getRefundStatusAttribute()
    {
        return static::$refundStatusMap[$this->attributes['refund_status']];
    }

    public function installment()
    {
        return $this->belongsTo(Installment::class, 'installment_id', 'id');
    }

    /**
     * 判断本期是否逾期
     * 过了最后还款日期那天后才算逾期
     *
     * @return bool
     */
    public function isOverdue()
    {
        return $this->due_date->isPast();
    }

    protected function getTotalAmountAttribute()
    {
        // 使用高精度计算
        return BigDecimal::of($this->base_amount)->plus($this->fee)->plus($this->fine ?? 0)->toScale(2)->toFloat();
    }
}
