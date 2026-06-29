<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Order;

class OrderStatusNotification extends Notification
{
    use Queueable;

    protected $order;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, string $status)
    {
        $this->order = $order;
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
        $orderNum = $this->order->order_number;
        $formattedStatus = ucfirst($this->status);
        $totalPrice = number_format($this->order->total_price, 2);

        $mail = (new MailMessage)
            ->subject("Order #{$orderNum} Update: {$formattedStatus}")
            ->greeting("Hello {$notifiable->name}!");

        switch ($this->status) {
            case 'pending':
                $mail->line("We have received your order #{$orderNum}! We are reviewing the details and preparing to assign a tailor.")
                     ->line("Total Price: ₹{$totalPrice}");
                break;
            case 'accepted':
                $mail->line("Great news! Your order #{$orderNum} has been accepted by our staff and is in queue.")
                     ->line("A skilled tailor is being assigned to bring your custom design to life.");
                break;
            case 'stitching':
                $mail->line("Your garments for order #{$orderNum} are now under active stitching by our master tailor!")
                     ->line("We are paying close attention to every detail to ensure the perfect fit.");
                break;
            case 'completed':
                $mail->line("Stitching complete! Your custom garment under order #{$orderNum} has been finished and quality-checked by our tailor.")
                     ->line("It is being packed carefully and handed over to our delivery staff.");
                break;
            case 'out_for_delivery':
                $mail->line("Out for Delivery! 🚚 Your custom garment under order #{$orderNum} is on its way to your address.")
                     ->line("Our delivery driver will reach you shortly. Please be available to receive your package.");
                break;
            case 'delivered':
                $mail->line("Delivered! 🎉 Your order #{$orderNum} has been successfully delivered and completed.")
                     ->line("We hope you love your new custom garment! Please take a moment to leave us a review on the portal.");
                break;
            default:
                $mail->line("Your order #{$orderNum} status has transitioned to: **{$formattedStatus}**.");
        }

        return $mail
            ->action('View Order Status', url('/dashboard'))
            ->line('Thank you for choosing our bespoke styling services!')
            ->salutation('Warmly, The Stitch & Style Team');
    }

    /**
     * Get the array representation of the notification for the database.
     */
    public function toDatabase($notifiable): array
    {
        $formattedStatus = ucfirst($this->status);
        $message = "Your order #{$this->order->order_number} is now {$this->status}.";
        
        switch ($this->status) {
            case 'pending':
                $message = "Your order #{$this->order->order_number} has been successfully placed!";
                break;
            case 'stitching':
                $message = "🧵 Stitching has started for your order #{$this->order->order_number}!";
                break;
            case 'completed':
                $message = "✨ Stitching complete for order #{$this->order->order_number}. Preparing package!";
                break;
            case 'out_for_delivery':
                $message = "🚚 Order #{$this->order->order_number} is out for delivery!";
                break;
            case 'delivered':
                $message = "🎉 Order #{$this->order->order_number} delivered successfully!";
                break;
        }

        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->status,
            'title' => "Order {$formattedStatus}",
            'message' => $message,
        ];
    }
}
