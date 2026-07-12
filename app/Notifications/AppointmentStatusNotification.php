<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Appointment;

class AppointmentStatusNotification extends Notification
{
    use Queueable;

    protected $appointment;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(Appointment $appointment, string $status)
    {
        $this->appointment = $appointment;
        $this->status = strtolower($status);
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $dateStr = $this->appointment->appointment_date ? $this->appointment->appointment_date->format('j F Y') : 'TBD';
        $formattedStatus = ucfirst($this->status);

        $mail = (new MailMessage)
            ->subject("Appointment Update: {$formattedStatus}")
            ->greeting("Hello {$notifiable->name}!");

        switch ($this->status) {
            case 'pending':
                $mail->line("Your request for a measurement visit on {$dateStr} has been received and is pending confirmation.");
                break;
            case 'confirmed':
                $mail->line("Great news! Your measurement visit appointment for {$dateStr} has been confirmed.")
                     ->line("Our staff member will visit your address at the scheduled time.");
                break;
            case 'in_progress':
                $mail->line("Our staff member is now on their way and has started the measurement visit for your appointment on {$dateStr}!");
                break;
            case 'completed':
                $mail->line("Your measurement visit appointment on {$dateStr} has been completed successfully.")
                     ->line("Your measurements have been updated on our portal.");
                break;
            case 'cancelled':
                $mail->line("Your appointment for {$dateStr} has been cancelled.");
                break;
            default:
                $mail->line("Your appointment status has transitioned to: **{$formattedStatus}**.");
        }

        return $mail
            ->action('View Appointment Details', rtrim(config('app.frontend_url'), '/') . '/dashboard?tab=appointments')
            ->line('Thank you for choosing our bespoke styling services!')
            ->salutation('Warmly, The SwiDhaagha Team');
    }

    /**
     * Get the array representation of the notification for the database.
     */
    public function toDatabase($notifiable): array
    {
        $formattedStatus = ucfirst($this->status);
        $dateStr = $this->appointment->appointment_date ? $this->appointment->appointment_date->format('j F Y') : 'TBD';
        $message = "Your measurement visit appointment is now {$this->status}.";
        
        switch ($this->status) {
            case 'pending':
                $message = "Your appointment request for {$dateStr} has been received and is pending confirmation.";
                break;
            case 'confirmed':
                $message = "✅ Your appointment for {$dateStr} has been confirmed! A staff member will visit you.";
                break;
            case 'in_progress':
                $message = "🏃 Staff has started the measurement visit for your appointment on {$dateStr}!";
                break;
            case 'completed':
                $message = "✨ Measurement visit completed successfully for your appointment on {$dateStr}!";
                break;
            case 'cancelled':
                $message = "❌ Your appointment for {$dateStr} has been cancelled.";
                break;
        }

        return [
            'appointment_id' => $this->appointment->id,
            'status' => $this->status,
            'title' => "Appointment {$formattedStatus}",
            'message' => $message,
        ];
    }
}
