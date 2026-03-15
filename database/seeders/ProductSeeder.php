<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Wireless Headphones',
                'description' => 'Premium wireless Bluetooth headphones with active noise cancellation, 30-hour battery life, and comfortable over-ear design.',
                'price' => 2499.00,
                'category' => 'Audio',
            ],
            [
                'name' => 'Laptop Stand',
                'description' => 'Ergonomic aluminum laptop stand with adjustable height and angle. Compatible with laptops up to 17 inches.',
                'price' => 1299.00,
                'category' => 'Accessories',
            ],
            [
                'name' => 'USB-C Hub',
                'description' => '7-in-1 USB-C hub with HDMI, USB 3.0, SD card reader, and 100W power delivery pass-through.',
                'price' => 899.00,
                'category' => 'Accessories',
            ],
            [
                'name' => 'Bluetooth Speaker',
                'description' => 'Portable waterproof Bluetooth speaker with 360-degree sound, 12-hour battery, and built-in microphone.',
                'price' => 1799.00,
                'category' => 'Audio',
            ],
            [
                'name' => 'Phone Case',
                'description' => 'Shock-resistant phone case with military-grade drop protection and slim profile. Available for iPhone and Samsung.',
                'price' => 499.00,
                'category' => 'Phone Accessories',
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['name' => $p['name']],
                $p
            );
        }
    }
}
