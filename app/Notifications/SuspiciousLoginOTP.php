<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SuspiciousLoginOTP extends Notification
{
    use Queueable;

    protected $otp;
    protected $ip;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp, string $ip)
    {
        $this->otp = $otp;
        $this->ip = $ip;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Security Alert: Login from Unrecognized IP - RLBmods')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('We detected a login attempt to your RLBmods account from a new IP address: ' . $this->ip)
            ->line('If this was you, please enter the following verification code to continue:')
            ->line('')
            ->line('# **' . $this->otp . '**')
            ->line('')
            ->line('This code will expire in **15 minutes**.')
            ->line('If you did NOT attempt to login, please change your password immediately and contact support.')
            ->salutation('The RLBmods Team Security');
    }
}
