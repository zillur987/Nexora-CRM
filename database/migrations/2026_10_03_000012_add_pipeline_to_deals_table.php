<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('deals', function (Blueprint $table) { $table->foreignUuid('pipeline_id')->nullable()->after('contact_id')->constrained('pipelines')->restrictOnDelete(); $table->foreignUuid('pipeline_stage_id')->nullable()->after('pipeline_id')->constrained('pipeline_stages')->restrictOnDelete(); $table->index(['pipeline_id','pipeline_stage_id']); }); }
    public function down(): void { Schema::table('deals', function (Blueprint $table) { $table->dropIndex(['pipeline_id','pipeline_stage_id']); $table->dropForeign(['pipeline_stage_id']); $table->dropForeign(['pipeline_id']); $table->dropColumn(['pipeline_id','pipeline_stage_id']); }); }
};
