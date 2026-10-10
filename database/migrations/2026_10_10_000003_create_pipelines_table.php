<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pipelines')) {
            Schema::create('pipelines', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(false)->index();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('deal_stages') && ! Schema::hasColumn('deal_stages', 'pipeline_id')) {
            Schema::table('deal_stages', function (Blueprint $table): void {
                $table->foreignId('pipeline_id')->nullable()->after('id')
                    ->constrained('pipelines')->nullOnDelete();
                $table->boolean('is_active')->default(true)->after('outcome');
            });
        }

        if (Schema::hasTable('deals') && ! Schema::hasColumn('deals', 'pipeline_id')) {
            Schema::table('deals', function (Blueprint $table): void {
                $table->foreignId('pipeline_id')->nullable()->after('deal_stage_id')
                    ->constrained('pipelines')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('deals') && Schema::hasColumn('deals', 'pipeline_id')) {
            Schema::table('deals', fn (Blueprint $table) => $table->dropConstrainedForeignId('pipeline_id'));
        }
        if (Schema::hasTable('deal_stages') && Schema::hasColumn('deal_stages', 'pipeline_id')) {
            Schema::table('deal_stages', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pipeline_id');
                $table->dropColumn('is_active');
            });
        }
        Schema::dropIfExists('pipelines');
    }
};
