<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 254);
            $table->string('phone', 30)->nullable();
            $table->string('company', 120)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('source', 30)->default('other');
            $table->string('status', 20)->default('new');
            $table->unsignedTinyInteger('score')->default(0);
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->text('notes')->nullable();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Uniqueness among live rows is enforced in the FormRequests (soft deletes make a DB-level unique awkward).
            $table->index('email');
            $table->index(['status', 'created_at']);
            $table->index('source');
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
