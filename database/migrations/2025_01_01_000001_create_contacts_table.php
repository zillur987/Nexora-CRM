<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 254)->unique();
            $table->string('phone', 30)->nullable();
            $table->string('company', 120)->nullable();
            $table->string('status', 20)->default('lead');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
