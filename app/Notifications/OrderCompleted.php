<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCompleted extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $product;
    protected $keys;

    /**
     * Create a new notification instance.
     */
    public function __construct(Product $product, array $keys)
    {
        $this->product = $product;
        $this->keys = $keys;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $keysText = implode("\n", array_map(fn($key) => "• {$key}", $this->keys));
        
        return (new MailMessage)
            ->subject('Order Complete - ' . $this->product->name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your order has been completed successfully!')
            ->line('**Product:** ' . $this->product->name)
            ->line('**Keys Generated:** ' . count($this->keys))
            ->line('')
            ->line('**Your Keys:**')
            ->line($keysText)
            ->line('')
            ->action('View Downloads', url('/downloads'))
            ->line('Thank you for your purchase!')
            ->salutation('The RLBmods Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Order complete! You generated " . count($this->keys) . " keys for {$this->product->name}.",
            'product_name' => $this->product->name,
            'keys' => $this->keys,
            'type' => 'success',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'message' => "Order complete! Click to view your {$this->product->name} keys.",
            'product_name' => $this->product->name,
            'keys' => $this->keys,
            'type' => 'success',
            'created_at' => now()->toISOString(),
        ]);
    }
}
