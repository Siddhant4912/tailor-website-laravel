<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AdminRecentActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $orders;
    public Collection $appointments;
    public int $windowMinutes;
    public float $totalOrderAmount;
    public string $generatedAt;

    /**
     * Create a new message instance.
     */
    public function __construct(Collection $orders, Collection $appointments, int $windowMinutes = 60)
    {
        $this->orders = $orders;
        $this->appointments = $appointments;
        $this->windowMinutes = $windowMinutes;
        $this->totalOrderAmount = (float) $orders->sum('total_amount');
        $this->generatedAt = now()->format('d M Y, h:i A');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $orderCount = $this->orders->count();
        $apptCount = $this->appointments->count();

        $parts = [];
        if ($orderCount > 0) {
            $parts[] = "{$orderCount} New Order" . ($orderCount > 1 ? 's' : '');
        }
        if ($apptCount > 0) {
            $parts[] = "{$apptCount} New Appointment" . ($apptCount > 1 ? 's' : '');
        }
        $summaryTitle = !empty($parts) ? implode(' & ', $parts) : 'Recent Activity';
        $timeLabel = ($this->windowMinutes === 60) ? 'Last 1 Hour' : "Last {$this->windowMinutes} Mins";

        return new Envelope(
            subject: "🔔 [SwiDhaagha] {$summaryTitle} ({$timeLabel})"
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_recent_activity',
            with: [
                'orders' => $this->orders,
                'appointments' => $this->appointments,
                'orderCount' => $this->orders->count(),
                'appointmentCount' => $this->appointments->count(),
                'totalOrderAmount' => $this->totalOrderAmount,
                'windowMinutes' => $this->windowMinutes,
                'generatedAt' => $this->generatedAt,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
