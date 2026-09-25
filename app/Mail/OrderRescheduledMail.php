<?php

namespace App\Mail;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderRescheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The schedule being replaced is passed in rather than read from the
     * order's original_pickup_* columns: on a second reschedule the customer
     * needs to see the date they were last told, not the very first one.
     */
    public function __construct(
        public Order $order,
        public CarbonInterface $previousPickupDate,
        public string $previousPickupSlot,
    ) {
        $this->order->loadMissing(['customer', 'items']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Siel Cart Order #'.$this->order->order_number.' Pickup Has Been Rescheduled',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.rescheduled',
            with: [
                'order' => $this->order,
                'previousPickupDate' => $this->previousPickupDate,
                'previousPickupSlot' => $this->previousPickupSlot,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
