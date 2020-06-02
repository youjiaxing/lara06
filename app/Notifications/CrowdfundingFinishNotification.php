<?php

namespace App\Notifications;

use App\Models\CrowdfundingProduct;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrowdfundingFinishNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var CrowdfundingProduct
     */
    protected $crowdfundingProduct;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->crowdfundingProduct = $this->order->items[0]->product->crowdfunding;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if ($this->crowdfundingProduct->status == CrowdfundingProduct::STATUS_SUCCESS) {
            return (new MailMessage)
                ->subject("众筹订单成功通知")
                ->greeting($this->order->user->name . ", 你好!")
                ->line(sprintf("您的众筹订单 \"%s\" 已成功, 巴拉巴拉....", $this->crowdfundingProduct->product->title))
                ->action('点击查看订单详情', route('orders.show', [$this->order]));
        } else {
            return (new MailMessage)
                ->subject("众筹订单失败通知")
                ->greeting($this->order->user->name . ", 你好!")
                ->line(sprintf("您的众筹订单 \"%s\" 由于未能在截至时间前完成目标, 巴拉巴拉....", $this->crowdfundingProduct->product->title))
                ->error()
                ->action('点击查看订单详情', route('orders.show', [$this->order]));
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
