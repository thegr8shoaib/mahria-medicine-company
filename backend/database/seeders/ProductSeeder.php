<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Panadol Extra', 'generic_name' => 'Paracetamol + Caffeine', 'price' => 120, 'cost_price' => 90, 'unit' => 'tablet', 'low_stock_alert' => 50],
            ['name' => 'Augmentin 625mg', 'generic_name' => 'Amoxicillin + Clavulanic Acid', 'price' => 550, 'cost_price' => 420, 'unit' => 'tablet', 'low_stock_alert' => 20],
            ['name' => 'Brufen 400mg', 'generic_name' => 'Ibuprofen', 'price' => 90, 'cost_price' => 65, 'unit' => 'tablet', 'low_stock_alert' => 40],
            ['name' => 'Panadol Syrup 120ml', 'generic_name' => 'Paracetamol', 'price' => 180, 'cost_price' => 135, 'unit' => 'syrup', 'low_stock_alert' => 15],
            ['name' => 'Insulin Glargine 100IU', 'generic_name' => 'Insulin Glargine', 'price' => 1450, 'cost_price' => 1200, 'unit' => 'injection', 'low_stock_alert' => 10],
            ['name' => 'Zincovit Tablets', 'generic_name' => 'Multivitamin + Zinc', 'price' => 260, 'cost_price' => 200, 'unit' => 'tablet', 'low_stock_alert' => 30],
            ['name' => 'Prospan Cough Syrup', 'generic_name' => 'Ivy Leaf Extract', 'price' => 320, 'cost_price' => 250, 'unit' => 'syrup', 'low_stock_alert' => 12],
            ['name' => 'Flagyl 400mg', 'generic_name' => 'Metronidazole', 'price' => 110, 'cost_price' => 80, 'unit' => 'tablet', 'low_stock_alert' => 40],
            ['name' => 'Calpol 500mg', 'generic_name' => 'Paracetamol', 'price' => 95, 'cost_price' => 70, 'unit' => 'tablet', 'low_stock_alert' => 35],
            ['name' => 'Zyrtec 10mg', 'generic_name' => 'Cetirizine', 'price' => 140, 'cost_price' => 105, 'unit' => 'tablet', 'low_stock_alert' => 25],
            ['name' => 'Betnovate N Ointment', 'generic_name' => 'Betamethasone + Neomycin', 'price' => 210, 'cost_price' => 160, 'unit' => 'ointment', 'low_stock_alert' => 10],
            ['name' => 'Salinax Nasal Drops', 'generic_name' => 'Normal Saline 0.65%', 'price' => 75, 'cost_price' => 55, 'unit' => 'drops', 'low_stock_alert' => 20],
        ];

        foreach ($products as $index => $data) {
            $product = Product::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'sku' => 'SKU-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'barcode' => '890' . rand(100000000, 999999999),
                ])
            );

            if ($product->batches()->count() === 0) {
                $expiry = Carbon::today()->addMonths(rand(4, 18));

                Batch::create([
                    'product_id' => $product->id,
                    'batch_number' => 'B' . date('ym') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'quantity' => rand(60, 300),
                    'expiry_date' => $expiry,
                ]);
            }
        }
    }
}