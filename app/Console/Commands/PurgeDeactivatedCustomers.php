<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;

class PurgeDeactivatedCustomers extends Command
{
    protected $signature = 'customers:purge-deactivated';
    protected $description = 'Anonymize email addresses for customer accounts deactivated for 30+ days.';

    public function handle(): void
    {
        $cutoffDate = now()->subDays(30);

        $expiredCustomers = Customer::query()
            ->where('is_active', false)
            ->whereNotNull('deactivated_at')
            ->where('deactivated_at', '<=', $cutoffDate)
            ->where('email', 'not like', 'deleted_%')
            ->get();

        $count = 0;

        foreach ($expiredCustomers as $customer) {
            $customer->email = 'deleted_' . time() . '_' . $customer->email;
            $customer->save();
            $count++;
        }

        $this->info("Processed {$count} expired customer account(s).");
    }
}