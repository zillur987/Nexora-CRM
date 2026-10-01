<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 160);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('stage', 20)->default('new');
            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->timestamps();

            $table->index('stage');
            $table->index(['contact_id', 'stage']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
