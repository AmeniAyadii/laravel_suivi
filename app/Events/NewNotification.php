<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('notifications.' . $this->notification->user_id);
    }

    public function broadcastAs()
    {
        return 'new-notification';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->notification->id,
            'titre' => $this->notification->titre,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
            'icon' => $this->notification->icon,
            'couleur' => $this->notification->couleur,
            'date_envoi' => $this->notification->date_envoi,
        ];
    }
}
