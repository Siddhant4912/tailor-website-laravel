<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SendOtpNotification extends Notification
{
    use Queueable;

    protected $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Account - One-Time Password (OTP)')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Thank you for choosing our Tailor & Styling services. To secure your account and verify your action, please use the following One-Time Password (OTP):')
            ->line('')
            ->line('Your Verification Code: **' . $this->otp . '**')
            ->line('')
            ->line('This verification code is valid for the next 15 minutes. Please do not share this OTP with anyone.')
            ->line('If you did not request this verification, please ignore this email or contact support.')
            ->salutation('Warm regards, The Tailor Team');
    }
}
