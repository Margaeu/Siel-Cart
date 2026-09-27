<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Report;
use App\Models\Review;

/** Shares badge counts between the sidebar and its poller within one request. */
class AdminNavigationBadges
{
    /** @var array<class-string, ?string> */
    private array $counts = [];

    public function pending(string $model): ?string
    {
        if (! array_key_exists($model, $this->counts)) {
            $count = match ($model) {
                Order::class => Order::ofStatus('pending')->count(),
                Review::class => Review::where('is_approved', false)->count(),
                Report::class => Report::where('status', 'pending')->count(),
            };

            $this->counts[$model] = $count > 0 ? (string) $count : null;
        }

        return $this->counts[$model];
    }

    public function forget(): void
    {
        $this->counts = [];
    }
}
