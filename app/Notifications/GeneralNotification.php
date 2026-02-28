<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    // Tell Laravel to save this in the Database and Broadcast
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    // This is the data that will be stored in the 'data' column
    public function toArray($notifiable)
    {
        return [
            'message' => $this->message,
            'type' => 'info'
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'message' => $this->message,
            'type' => 'info',
            'created_at' => now()->toISOString(),
        ]);
    }
}