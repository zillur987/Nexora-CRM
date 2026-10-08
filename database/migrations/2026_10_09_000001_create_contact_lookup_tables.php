<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** User-extensible dropdown values for the "Contact Source +" and "Contact Stage +" fields. */
return new class extends Migration
{
    private const TABLES = ['contact_sources', 'contact_stages'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique(); // utf8mb4_unicode_ci => case-insensitive unique
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::dropIfExists($name);
        }
    }
};
