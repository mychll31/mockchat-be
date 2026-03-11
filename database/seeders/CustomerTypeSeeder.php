<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'type_key' => 'normal_buyer',
                'label' => 'Normal Customer',
                'description' => 'A friendly customer interested in purchasing a product.',
                'personality' => 'Friendly, polite, interested in buying. Asks about features, pricing, availability. Tagalog.',
            ],
            [
                'type_key' => 'irate_returner',
                'label' => 'Irate Customer (Return)',
                'description' => 'An angry customer who wants to return a defective product.',
                'personality' => 'Angry, frustrated, wants return/refund. Impatient but can calm down. Tagalog.',
            ],
            [
                'type_key' => 'irate_annoyed',
                'label' => 'Irate Customer (Annoyed)',
                'description' => 'A customer extremely annoyed with the sales agent.',
                'personality' => 'Hostile, sarcastic, feels transferred too many times. Tagalog.',
            ],
        ];

        foreach ($types as $t) {
            CustomerType::updateOrCreate(
                ['type_key' => $t['type_key']],
                $t
            );
        }
    }
}
