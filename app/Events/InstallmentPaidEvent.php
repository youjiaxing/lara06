<?php

namespace App\Events;

use App\Models\InstallmentItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstallmentPaidEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $installmentItem;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(InstallmentItem $item)
    {
        $this->installmentItem = $item;
    }

    /**
     * @return InstallmentItem
     */
    public function getInstallmentItem()
    {
        return $this->installmentItem;
    }
}
