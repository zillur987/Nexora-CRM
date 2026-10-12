<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safe to run on a project that already has a tasks table: tables are created only when missing,
 * and any missing column is added one by one. Existing columns and data are never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->sync('task_types', $this->optionColumns());
        $this->sync('task_priorities', $this->optionColumns());
        $this->sync('task_statuses', $this->optionColumns() + [
            'is_closed' => fn (Blueprint $t) => $t->boolean('is_closed')->default(false),
        ]);
        $this->sync('tasks', $this->taskColumns());
    }

    public function down(): void
    {
        // Only drop what this migration would have created from scratch; never drop a table you already had.
        // (Left empty on purpose: rolling back must not destroy existing task data.)
    }

    /** Create the table with just an id when missing, then add every missing column. */
    private function sync(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $t): void {
                $t->id();
                $t->timestamps();
            });
        }

        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $define($t));
            }
        }
    }

    private function optionColumns(): array
    {
        return [
            'name' => fn (Blueprint $t) => $t->string('name', 80)->default(''),
            'color' => fn (Blueprint $t) => $t->string('color', 9)->nullable(),
            'sort_order' => fn (Blueprint $t) => $t->unsignedSmallInteger('sort_order')->default(0)->index(),
            'is_default' => fn (Blueprint $t) => $t->boolean('is_default')->default(false),
            'is_active' => fn (Blueprint $t) => $t->boolean('is_active')->default(true),
        ];
    }

    private function taskColumns(): array
    {
        return [
            'name' => fn (Blueprint $t) => $t->string('name', 180)->default('')->index(),
            'assignee_id' => fn (Blueprint $t) => $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete(),
            // Linked CRM records: plain indexed ids (no FK) so this works whatever your table names/engines are.
            'company_id' => fn (Blueprint $t) => $t->unsignedBigInteger('company_id')->nullable()->index(),
            'contact_id' => fn (Blueprint $t) => $t->unsignedBigInteger('contact_id')->nullable()->index(),
            'opportunity_id' => fn (Blueprint $t) => $t->unsignedBigInteger('opportunity_id')->nullable()->index(),
            'quote_id' => fn (Blueprint $t) => $t->unsignedBigInteger('quote_id')->nullable()->index(),
            'task_type_id' => fn (Blueprint $t) => $t->foreignId('task_type_id')->nullable()->constrained('task_types')->nullOnDelete(),
            'task_priority_id' => fn (Blueprint $t) => $t->foreignId('task_priority_id')->nullable()->constrained('task_priorities')->nullOnDelete(),
            'task_status_id' => fn (Blueprint $t) => $t->foreignId('task_status_id')->nullable()->constrained('task_statuses')->nullOnDelete(),
            'due_date' => fn (Blueprint $t) => $t->date('due_date')->nullable()->index(),
            'description' => fn (Blueprint $t) => $t->text('description')->nullable(),
            'completed_at' => fn (Blueprint $t) => $t->timestamp('completed_at')->nullable(),
            'created_by' => fn (Blueprint $t) => $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(),
        ];
    }
};
