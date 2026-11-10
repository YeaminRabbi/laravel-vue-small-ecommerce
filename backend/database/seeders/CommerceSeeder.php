<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(['email' => 'owner@example.com'], ['name' => 'Ayesha Rahman', 'password' => 'password']);
        $owner->syncRoles(['shop_owner']);
        $shop = Shop::firstOrCreate(['slug' => 'everyday-studio'], ['owner_id' => $owner->id, 'name' => 'Everyday Studio', 'description' => 'Thoughtful essentials for everyday living.']);
        $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
        foreach ([
            ['Ceramic coffee mug', 'HOME-001', 'A warm, handcrafted start to your morning.', 650],
            ['Canvas everyday tote', 'BAG-001', 'A durable cotton tote for your daily essentials.', 890],
            ['Linen cushion cover', 'HOME-002', 'Soft natural linen with a relaxed finish.', 1200],
            ['Oak desk organizer', 'DESK-001', 'Keep your workspace simple and organized.', 1450],
            ['Classic notebook', 'DESK-002', 'A little space for your next big idea.', 350],
        ] as [$name, $sku, $description, $price]) {
            $product = Product::firstOrCreate(['sku' => $sku], ['created_by' => $admin->id, 'name' => $name, 'description' => $description, 'price' => $price]);
            if ($sku !== 'DESK-002') {
                $shop->listings()->firstOrCreate(['product_id' => $product->id], ['name' => $name, 'description' => $description, 'price' => $price, 'is_active' => true]);
            }
        }
    }
}
