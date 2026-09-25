<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'notes',
    ];

    /**
     * Split a reschedule note back into its parts for display.
     *
     * Order::reschedulePickup() writes the move as one sentence, e.g.
     * "Pickup rescheduled from Sep 26, 2026 (8:00 AM - 5:00 PM) to
     * Sep 27, 2026 (8:00 AM - 5:00 PM). Reason: ...", which is unreadable
     * as a single line in the panel. The two must change together. A note
     * that does not match (hand-edited or older wording) comes back with
     * null dates and the whole note as the reason, so nothing is hidden.
     *
     * @return array{from_date: ?string, from_slot: ?string, to_date: ?string, to_slot: ?string, reason: ?string}
     */
    public function rescheduleDetails(): array
    {
        $pattern = '/^'.preg_quote(Order::RESCHEDULE_NOTE_PREFIX, '/')
            .' from (.+?) \((.+?)\) to (.+?) \((.+?)\)\.(?: Reason: (.*))?$/s';

        if (! preg_match($pattern, (string) $this->notes, $matches)) {
            return [
                'from_date' => null,
                'from_slot' => null,
                'to_date' => null,
                'to_slot' => null,
                'reason' => $this->notes,
            ];
        }

        return [
            'from_date' => $matches[1],
            'from_slot' => $matches[2],
            'to_date' => $matches[3],
            'to_slot' => $matches[4],
            'reason' => filled($matches[5] ?? null) ? $matches[5] : null,
        ];
    }

    // relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
