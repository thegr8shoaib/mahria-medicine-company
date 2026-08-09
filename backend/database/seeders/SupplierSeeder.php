<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'MedPlus Distributors', 'phone' => '0300-1111111', 'email' => 'sales@medplus.pk', 'address' => 'Karachi, Pakistan'],
            ['name' => 'PharmaCare Ltd.', 'phone' => '0301-2222222', 'email' => 'info@pharmacare.pk', 'address' => 'Lahore, Pakistan'],
            ['name' => 'HealthLink Traders', 'phone' => '0302-3333333', 'email' => 'orders@healthlink.pk', 'address' => 'Islamabad, Pakistan'],
            ['name' => 'BioMed Suppliers', 'phone' => '0303-4444444', 'email' => null, 'address' => 'Rawalpindi, Pakistan'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['name' => $supplier['name']], $supplier);
        }
    }
}