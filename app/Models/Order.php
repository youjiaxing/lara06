<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * Class Order
 * @package App\Models
 *
 * @property string           $no            订单流水号
 * @property array            $address       收货地址数组
 * @property string           $type          订单类型: 普通订单、众筹订单、秒杀订单
 * @property string           $type_str      订单商品类型
 * @property Carbon           $paid_at       支付时间(用于判断是否已支付)
 * @property bool             $closed        订单是否已关闭
 * @property float            $total_amount
 * @property string           $remark        下单备注
 * @property string           $payment_method
 * @property string           $payment_no
 * @property string           $refund_status 退款状态
 * @property string           $refund_no     退款编号
 * @property boolean          $reviewed      是否已评价
 * @property string           $ship_status
 * @property string           $ship_data
 * @property array            $extra
 *
 *
 * @property-read User        $user
 * @property-read Installment $installment
 * @property-read string      $payment_method_str
 */
class Order extends Model
{
    const REFUND_STATUS_PENDING = 'pending';
    const REFUND_STATUS_APPLIED = 'applied';
    const REFUND_STATUS_PROCESSING = 'processing';
    const REFUND_STATUS_SUCCESS = 'success';
    const REFUND_STATUS_FAILED = 'failed';

    const SHIP_STATUS_PENDING = 'pending';
    const SHIP_STATUS_DELIVERED = 'delivered';
    const SHIP_STATUS_RECEIVED = 'received';

    const TYPE_NORMAL = 'normal';
    const TYPE_CROWDFUNDING = 'crowdfunding';

    const PAYMENT_METHOD_ALIPAY = 'alipay';
    const PAYMENT_METHOD_WECHAT = 'wechat';
    const PAYMENT_METHOD_INSTALLMENT = 'installment';

    public static $refundStatusMap = [
        self::REFUND_STATUS_PENDING => '未退款',
        self::REFUND_STATUS_APPLIED => '已申请退款',
        self::REFUND_STATUS_PROCESSING => '退款中',
        self::REFUND_STATUS_SUCCESS => '退款成功',
        self::REFUND_STATUS_FAILED => '退款失败',
    ];

    public static $shipStatusMap = [
        self::SHIP_STATUS_PENDING => '未发货',
        self::SHIP_STATUS_DELIVERED => '已发货',
        self::SHIP_STATUS_RECEIVED => '已收货',
    ];

    private static $typeMap = [
        self::TYPE_NORMAL => '普通商品订单',
        self::TYPE_CROWDFUNDING => '众筹商品订单',
    ];

    private static $paymentMethodStr = [
        self::PAYMENT_METHOD_ALIPAY => '支付宝',
        self::PAYMENT_METHOD_WECHAT => '微信',
        self::PAYMENT_METHOD_INSTALLMENT => '分期付款',
        null => '未支付',
    ];

    protected $fillable = [
        'no',
        'address',
        'total_amount',
        'remark',
        'paid_at',
        'payment_method',
        'payment_no',
        'refund_status',
        'refund_no',
        'closed',
        'reviewed',
        'ship_status',
        'ship_data',
        'extra',
        'type',
    ];

    protected $casts = [
        'closed' => 'boolean',
        'reviewed' => 'boolean',
        'address' => 'json',
        'ship_data' => 'json',
        'extra' => 'json',
    ];

    protected $dates = [
        'paid_at',
    ];

    public static function getAvailableRefundNo()
    {
        do {
            // Uuid类可以用来生成大概率不重复的字符串
            $no = Uuid::uuid4()->getHex();
            // 为了避免重复我们在生成之后在数据库中查询看看是否已经存在相同的退款订单号
        } while (self::query()->where('refund_no', $no)->exists());

        return $no;
    }

    protected static function boot()
    {
        parent::boot();
        // 监听模型创建事件，在写入数据库之前触发
        static::creating(
            function ($model) {
                // 如果模型的 no 字段为空
                if (!$model->no) {
                    // 调用 findAvailableNo 生成订单流水号
                    $model->no = static::findAvailableNo();
                    // 如果生成失败，则终止创建订单
                    if (!$model->no) {
                        return false;
                    }
                }
            }
        );
    }

    public static function findAvailableNo()
    {
        // 订单流水号前缀
        $prefix = date('YmdHis');
        for ($i = 0; $i < 10; $i++) {
            // 随机生成 6 位的数字
            $no = $prefix . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // 判断是否已经存在
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
        }
        \Log::warning('find order no failed');

        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function couponCode()
    {
        return $this->belongsTo(CouponCode::class);
    }

    public function isNormalOrder()
    {
        return $this->type === self::TYPE_NORMAL;
    }

    public function isCrowdfundingOrder()
    {
        return $this->type === self::TYPE_CROWDFUNDING;
    }

    public function installment()
    {
        return $this->hasOne(Installment::class, 'order_id', 'id');
    }

    protected function getTypeStrAttribute()
    {
        return static::$typeMap[$this->attributes['type']];
    }

    protected function getPaymentMethodStrAttribute()
    {
        return static::$paymentMethodStr[$this->attributes['payment_method']];
    }
}
