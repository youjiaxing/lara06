<?php

namespace App\Models;

use App\Exceptions\InternalException;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * 分期还款子表
 *
 * Class InstallmentItems
 * @package App\Models
 *
 * @property int              $id
 * @property int              $installment_id
 * @property int              $sequence           分期还款序号
 * @property float            $base_amount        当期还款订单金额
 * @property float            $fee                当期还款手续费
 * @property float            $fine               当期还款逾期费, 在还款前该值为0
 * @property Carbon|null      $due_date           当期还款截至时间
 * @property Carbon|null      $paid_at            当期实际还款时间
 * @property string           $payment_method     还款方式
 * @property string           $payment_no         第三方支付单号
 * @property string           $refund_status      退款状态
 *
 * @property-read string      $refund_status_str  还款状态说明
 * @property-read Installment $installment
 * @property-read float       $total_amount       本期应还总费用
 * @property-read string      $payment_method_str
 * @property-read string      $no                 构建出来的分期子订单号(由分期流水号拼接上分期序号), eg. "xxxxxxx_1"
 * @property-read string      $refund_no          构建出来的分期子退款单号(由)
 */
class InstallmentItem extends Model
{
    const REFUND_STATUS_PENDING = 'pending';
    const REFUND_STATUS_PROCESSING = 'processing';
    const REFUND_STATUS_SUCCESS = 'success';
    const REFUND_STATUS_FAILED = 'failed';

    const PAYMENT_METHOD_ALIPAY = 'alipay';
    const PAYMENT_METHOD_WECHAT = 'wechat';

    protected static $refundStatusMap = [
        self::REFUND_STATUS_PENDING => '未退款',
        self::REFUND_STATUS_PROCESSING => '退款中',
        self::REFUND_STATUS_SUCCESS => '退款成功',
        self::REFUND_STATUS_FAILED => '退款失败',
    ];

    private static $paymentMethodStr = [
        self::PAYMENT_METHOD_ALIPAY => '支付宝',
        self::PAYMENT_METHOD_WECHAT => '微信',
        null => '未支付',
    ];

    protected $guarded = [];

    protected $dates = [
        'due_date',
        'paid_at',
    ];

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

    protected function getRefundStatusStrAttribute()
    {
        return static::$refundStatusMap[$this->attributes['refund_status']];
    }

    protected function getTotalAmountAttribute()
    {
        // 使用高精度计算
        return BigDecimal::of($this->base_amount)->plus($this->fee)->plus($this->fine ?? 0)->toScale(2)->toFloat();
    }

    /**
     * @return string
     */
    protected function getNoAttribute()
    {
        $installmentNo = $this->installment->no;
        if (!$installmentNo) {
            throw new InternalException("需先设置分期主表的流水号");
        }
        return $installmentNo . '_' . $this->sequence;
    }

    protected function getRefundNoAttribute()
    {
        $orderRefundNo = $this->installment->order->refund_no;
        if (!$orderRefundNo) {
            throw new InternalException("需先设置订单的退款流水号");
        }
        return $orderRefundNo . '_' . $this->sequence;
    }

    protected function getPaymentMethodStrAttribute()
    {
        return static::$paymentMethodStr[$this->attributes['payment_method']];
    }
}
