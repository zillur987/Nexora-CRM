<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Header navigation, one self-referencing tree:
        //   link   (top level)       -> plain menu link
        //   mega   (top level)       -> menu with a mega panel
        //   column (child of mega)   -> column heading inside the panel
        //   item   (child of column) -> link (name + short description) inside a column
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('location', 20)->default('header');
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('label', 120);
            $table->string('description', 160)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('target', 10)->default('_self');

            // Mega panel extras (only used when type = mega)
            $table->string('meta_text', 160)->nullable();
            $table->string('feature_label', 80)->nullable();
            $table->string('feature_title', 120)->nullable();
            $table->string('feature_sub', 160)->nullable();
            $table->string('feature_url', 2048)->nullable();
            $table->string('feature_image')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['location', 'parent_id', 'sort_order']);
        });

        // Slides of the banner slider (the "Signature residences" spotlight).
        Schema::create('banner_slides', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);                       // tab label
            $table->string('location_label', 120)->nullable();
            $table->json('title_lines');                      // 1-3 short lines, animated one by one
            $table->text('text')->nullable();
            $table->json('facts')->nullable();                // up to 4 { value, label }
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('image')->nullable();
            $table->string('gradient', 1)->default('a');      // fallback while the image loads
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // One row per settings group (header, hero) holding a JSON document.
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40)->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('banner_slides');
        Schema::dropIfExists('menu_items');
    }
};
