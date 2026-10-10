<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the deals table up to the full deal profile.
 *
 * Every column is added only when it is missing, so this is safe to run on top of the original
 * deals table (whatever columns it already has are left untouched). New columns are nullable at
 * the database level; required-ness lives in DealRules.
 */
return new class extends Migration
{
    private const TABLE = 'deals';

    /** Dropped by down(). name / amount / company_id / contact_id / description may pre-date this migration, so they are kept. */
    private const OWNED_COLUMNS = [
        'currency', 'probability', 'expected_close_date', 'actual_close_date',
        'priority', 'next_step', 'lost_reason',
    ];

    private const OWNED_FOREIGN_KEYS = ['owner_id', 'deal_stage_id', 'deal_type_id', 'lead_source_id'];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        // Older schemas used `title` / `user_id`: carry them over to `name` / `owner_id`.
        if (Schema::hasColumn(self::TABLE, 'title') && ! Schema::hasColumn(self::TABLE, 'name')) {
            Schema::table(self::TABLE, fn (Blueprint $table) => $table->renameColumn('title', 'name'));
        }

        $addedOwner = ! Schema::hasColumn(self::TABLE, 'owner_id');

        Schema::table(self::TABLE, function (Blueprint $table) {
            $this->string($table, 'name', 150);

            if (! Schema::hasColumn(self::TABLE, 'amount')) {
                $table->decimal('amount', 15, 2)->nullable();
            }
            $this->string($table, 'currency', 3);

            if (! Schema::hasColumn(self::TABLE, 'probability')) {
                $table->unsignedTinyInteger('probability')->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'expected_close_date')) {
                $table->date('expected_close_date')->nullable()->index();
            }
            if (! Schema::hasColumn(self::TABLE, 'actual_close_date')) {
                $table->date('actual_close_date')->nullable();
            }

            $this->foreign($table, 'owner_id', 'users');
            $this->foreign($table, 'company_id', 'companies');
            $this->foreign($table, 'contact_id', 'contacts');
            $this->foreign($table, 'deal_stage_id', 'deal_stages');
            $this->foreign($table, 'deal_type_id', 'deal_types');
            $this->foreign($table, 'lead_source_id', 'contact_sources'); // sources are shared with contacts

            $this->string($table, 'priority', 10);
            $this->string($table, 'next_step', 255);
            $this->string($table, 'lost_reason', 255);

            if (! Schema::hasColumn(self::TABLE, 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if ($addedOwner && Schema::hasColumn(self::TABLE, 'user_id')) {
            DB::table(self::TABLE)->update(['owner_id' => DB::raw('user_id')]);
        }
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (self::OWNED_FOREIGN_KEYS as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            $columns = array_filter(self::OWNED_COLUMNS, fn (string $c) => Schema::hasColumn(self::TABLE, $c));

            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }

    private function string(Blueprint $table, string $column, int $length): void
    {
        if (! Schema::hasColumn(self::TABLE, $column)) {
            $table->string($column, $length)->nullable();
        }
    }

    private function foreign(Blueprint $table, string $column, string $references): void
    {
        if (! Schema::hasColumn(self::TABLE, $column)) {
            $table->foreignId($column)->nullable()->constrained($references)->nullOnDelete();
        }
    }
};
