<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'is_active']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku', 80)->unique();
            $table->string('barcode', 120)->nullable()->unique();
            $table->string('name', 180)->index();
            $table->string('slug', 200)->unique();
            $table->string('product_type', 30)->default('physical')->index();
            $table->string('brand', 120)->nullable()->index();
            $table->string('unit_of_measure', 40)->default('each');
            $table->string('currency', 3)->default('USD');
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->string('weight_unit', 12)->default('kg');
            $table->json('dimensions')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('track_inventory')->default(false)->index();
            $table->decimal('quantity_on_hand', 15, 3)->default(0);
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_active']);
            $table->index(['product_type', 'is_active']);
            $table->index(['track_inventory', 'quantity_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
