<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('pipeline_stages', function (Blueprint $table) { $table->uuid('id')->primary(); $table->foreignUuid('pipeline_id')->constrained('pipelines')->cascadeOnDelete(); $table->string('name',80); $table->string('slug',100); $table->unsignedSmallInteger('position'); $table->string('color',30)->default('secondary'); $table->unsignedTinyInteger('probability')->default(0); $table->boolean('is_won')->default(false); $table->boolean('is_lost')->default(false); $table->timestamps(); $table->unique(['pipeline_id','slug']); $table->unique(['pipeline_id','position']); $table->index(['pipeline_id','position']); }); }
    public function down(): void { Schema::dropIfExists('pipeline_stages'); }
};
