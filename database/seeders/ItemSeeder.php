<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        Item::factory(40)->highStock()->create();
        Item::factory(10)->lowStock()->create();
    }
}
