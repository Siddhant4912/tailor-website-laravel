<?php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class UserCreatedNotification extends Notification
{
    protected $password;

    public function __construct($password) { $this->password = $password; }

    public function via($notifiable) { return ['mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Account Created')
            ->greeting('Hello '.$notifiable->name)
            ->line('Your account has been created.')
            ->line('Your password: '.$this->password);
    }
}