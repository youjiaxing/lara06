<?php

namespace App\Notifications;

use App\Models\InstallmentItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstallmentPaidNotification extends Notification
{
    use Queueable;

    protected $item;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(InstallmentItem $item)
    {
        $this->item = $item;
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
        $item = $this->item;
        $installment = $item->installment;

        $mailMsg = (new MailMessage)
            ->subject("分期订单支付成功通知")
            ->greeting($item->installment->user->name)
            ->line(sprintf("您的分期订单 %s(%d/%d) 已支付成功", $installment->no, $item->sequence, $installment->count))
            ->action("点击查看分期详情", route('installments.show', [$installment->id]));

        if ($item->sequence == $installment->count) {
            $mailMsg->line(sprintf("分期订单已全部支付."));
        } else {
            /* @var InstallmentItem $nextItem */
            $nextItem = $installment->items()->where('sequence', $item->sequence + 1)->first();
            $mailMsg->line(sprintf("您的下一期分期支付截止时间: %s, 支付金额: %.2f", $nextItem->due_date, $nextItem->total_amount));
        }

        return $mailMsg;
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
