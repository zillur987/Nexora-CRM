<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Software', 'slug' => 'software', 'description' => 'Digital products and subscriptions'],
            ['name' => 'Hardware', 'slug' => 'hardware', 'description' => 'Physical devices and equipment'],
            ['name' => 'Services', 'slug' => 'services', 'description' => 'Professional and recurring services'],
        ];

        foreach ($categories as $category) {
            ProductCategory::query()->firstOrCreate(['slug' => $category['slug']], $category + ['is_active' => true]);
        }

        $software = ProductCategory::query()->where('slug', 'software')->value('id');
        Product::query()->firstOrCreate(
            ['sku' => 'DEMO-SUB-001'],
            [
                'category_id' => $software,
                'name' => 'CRM Starter Subscription',
                'slug' => 'crm-starter-subscription',
                'product_type' => 'service',
                'brand' => 'Internal',
                'unit_of_measure' => 'month',
                'currency' => 'USD',
                'sale_price' => '29.00',
                'cost_price' => '8.00',
                'tax_rate' => '0.0000',
                'short_description' => 'Example catalog item; edit or remove after setup.',
                'track_inventory' => false,
                'quantity_on_hand' => 0,
                'reorder_level' => 0,
                'is_active' => true,
                'tags' => ['demo', 'subscription'],
            ]
        );
    }
}
