<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['name' => 'Walk-in Customer', 'phone' => null, 'email' => null, 'address' => null],
            ['name' => 'Ahmed Khan', 'phone' => '0311-5555555', 'email' => 'ahmed@gmail.com', 'address' => 'Gulshan, Karachi'],
            ['name' => 'Fatima Noor', 'phone' => '0322-6666666', 'email' => 'fatima@yahoo.com', 'address' => 'DHA Phase 5, Lahore'],
            ['name' => 'Usman Ali', 'phone' => '0333-7777777', 'email' => null, 'address' => 'F-8, Islamabad'],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(['name' => $customer['name']], $customer);
        }
    }
}