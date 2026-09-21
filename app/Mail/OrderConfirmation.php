<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Theme;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load(['items.product','customer']);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Order Confirmation - ' . $this->order->order_number,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Mirrors resources/views/partials/theme-styles.blade.php so the
        // email always matches whatever's set in Admin -> Design, instead
        // of a color hardcoded here that can silently drift out of sync.
        $activeTheme = Theme::active()->first();

        $primaryColor = $activeTheme?->primary_color
            ?? Setting::get('primary_color', '#1E6031');

        $secondaryColor = $activeTheme?->secondary_color
            ?? Setting::get('secondary_color', '#E0A70D');

        return new Content(
            view: 'mail.order-confirmation',
            with: [
                'order' => $this->order,
                'primaryColor' => $primaryColor,
                'secondaryColor' => $secondaryColor,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}