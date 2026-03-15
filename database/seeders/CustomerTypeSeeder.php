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
            [
                'type_key' => 'confused',
                'label' => 'Confused Customer',
                'description' => 'A customer who is unsure about what they need.',
                'personality' => 'Confused, asks many questions, needs guidance. Tagalog.',
            ],
            [
                'type_key' => 'impatient',
                'label' => 'Impatient Customer',
                'description' => 'A customer who wants quick answers and fast service.',
                'personality' => 'Impatient, rushes the agent, wants quick resolution. Tagalog.',
            ],
            [
                'type_key' => 'friendly',
                'label' => 'Friendly Customer',
                'description' => 'A very pleasant and chatty customer.',
                'personality' => 'Very friendly, talkative, appreciative, easy to build rapport with. Tagalog.',
            ],
            [
                'type_key' => 'skeptical',
                'label' => 'Skeptical Customer',
                'description' => 'A customer who doubts claims and needs proof.',
                'personality' => 'Doubtful, questions everything, needs evidence and proof before buying. Tagalog.',
            ],
            [
                'type_key' => 'demanding',
                'label' => 'Demanding Customer',
                'description' => 'A customer with very high expectations.',
                'personality' => 'High expectations, wants premium treatment, complains about small issues. Tagalog.',
            ],
            [
                'type_key' => 'indecisive',
                'label' => 'Indecisive Customer',
                'description' => 'A customer who cannot make up their mind.',
                'personality' => 'Cannot decide, keeps changing mind, needs reassurance and guidance. Tagalog.',
            ],
            [
                'type_key' => 'bargain_hunter',
                'label' => 'Bargain Hunter',
                'description' => 'A customer always looking for the best deal.',
                'personality' => 'Price-conscious, always asks for discounts, compares with competitors. Tagalog.',
            ],
            [
                'type_key' => 'loyal',
                'label' => 'Loyal Customer',
                'description' => 'A returning customer who trusts the brand.',
                'personality' => 'Loyal repeat buyer, trusts the brand, expects loyalty rewards and recognition. Tagalog.',
            ],
            [
                'type_key' => 'first_time_buyer',
                'label' => 'First-Time Buyer',
                'description' => 'A customer making their first online purchase.',
                'personality' => 'New to online shopping, nervous, needs hand-holding and reassurance about process. Tagalog.',
            ],
            [
                'type_key' => 'silent',
                'label' => 'Silent/Unresponsive Customer',
                'description' => 'A customer who gives very short or no replies.',
                'personality' => 'Minimal replies, one-word answers, hard to engage, agent must draw them out. Tagalog.',
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
