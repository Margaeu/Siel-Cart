<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Address;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test customer
        $testCustomer = Customer::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => Hash::make('password'),
            'phone' => '+1234567890',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create 50 more customers
        $this->command->info('Creating customers...');
        $bar = $this->command->getOutput()->createProgressBar(50);

        for ($i = 0; $i < 50; $i++) {
            $customer = Customer::factory()->create();

            // Create 0-3 reviews per customer
            $reviewCount = rand(0, 3);
            for ($j = 0; $j < $reviewCount; $j++) {
                Review::factory()->create([
                    'customer_id' => $customer->id,
                    'product_id' => Product::inRandomOrder()->first()->id,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info('Customers created successfully!');
    }
}
