<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('pipelines', function (Blueprint $table) { $table->uuid('id')->primary(); $table->string('name',120); $table->string('slug',140)->unique(); $table->string('description',1000)->nullable(); $table->boolean('is_default')->default(false); $table->boolean('is_active')->default(true); $table->timestamps(); $table->index(['is_active','is_default']); }); }
    public function down(): void { Schema::dropIfExists('pipelines'); }
};
