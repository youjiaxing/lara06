<?php

namespace App\Listeners;

use App\Events\InstallmentPaidEvent;
use App\Notifications\InstallmentPaidNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendInstallmentItemPaidMail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  InstallmentPaidEvent  $event
     * @return void
     */
    public function handle(InstallmentPaidEvent $event)
    {
        $item = $event->getInstallmentItem();
        // if ($item->sequence == 1) {
        //     return;
        // }

        $item->installment->user->notify(new InstallmentPaidNotification($item));
    }
}
