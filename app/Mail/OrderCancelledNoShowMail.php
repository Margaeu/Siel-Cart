<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledNoShowMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['customer', 'items']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Siel Cart Order #'.$this->order->order_number.' Has Been Cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.cancelled-no-show',
            with: [
                'order' => $this->order,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
