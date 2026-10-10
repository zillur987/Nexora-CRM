<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-extensible dropdown values for the "Deal Stage +" and "Deal Type +" fields.
 *
 * Stages carry the pipeline behaviour: `sort_order` is the pipeline order, `probability` is the
 * default win chance applied when a deal enters the stage, and `outcome` (open | won | lost)
 * tells the app which stages close a deal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique(); // utf8mb4_unicode_ci => case-insensitive unique
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('outcome', 10)->default('open');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('deal_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_types');
        Schema::dropIfExists('deal_stages');
    }
};
