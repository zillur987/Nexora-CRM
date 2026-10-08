<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'contacts';

    /** Parent tables that must exist before this migration runs. */
    private const PARENT_TABLES = [
        'users',
        'companies',
        'industries',
        'contact_sources',
        'contact_stages',
    ];

    private const FOREIGN_COLUMNS = [
        'owner_id',
        'company_id',
        'industry_id',
        'contact_source_id',
        'contact_stage_id',
    ];

    private const ADDRESS_COLUMNS = [
        'address' => 255,
        'city' => 100,
        'zip' => 20,
        'state' => 100,
        'country' => 100,
    ];

    private const OWNED_STRINGS = [
        'job_title' => 150,
        'department' => 100,
        'linkedin' => 255,
        'twitter' => 255,
    ];

    public function up(): void
    {
        // Fail early with a clear message instead of a vague errno 150.
        foreach (self::PARENT_TABLES as $parent) {
            if (! Schema::hasTable($parent)) {
                throw new RuntimeException(
                    "Table `{$parent}` must be migrated before `".self::TABLE.'`.'
                );
            }
        }

        if (! Schema::hasTable(self::TABLE)) {
            // Other tables (e.g. deals.contact_id) may already hold foreign keys
            // pointing at `contacts` with a mismatched column type, which makes
            // InnoDB reject the new table with errno 150. Detach those keys,
            // create the table, then re-attach them with matching types.
            $detached = $this->detachReferencingForeignKeys();

            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id(); // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
                $table->timestamps();
            });

            $this->reattachForeignKeys($detached);
        } else {
            $this->normalizeExistingId();
        }

        $addedOwner = ! Schema::hasColumn(self::TABLE, 'owner_id');

        Schema::table(self::TABLE, function (Blueprint $table) {
            $this->string($table, 'first_name', 100);
            $this->string($table, 'last_name', 100);
            $this->string($table, 'email', 254);
            $this->string($table, 'phone', 30);

            foreach (self::OWNED_STRINGS as $column => $length) {
                $this->string($table, $column, $length);
            }

            if (! Schema::hasColumn(self::TABLE, 'birthday')) {
                $table->date('birthday')->nullable();
            }

            $this->foreign($table, 'owner_id', 'users');
            $this->foreign($table, 'company_id', 'companies');
            $this->foreign($table, 'industry_id', 'industries');
            $this->foreign($table, 'contact_source_id', 'contact_sources');
            $this->foreign($table, 'contact_stage_id', 'contact_stages');

            foreach (['present', 'permanent'] as $group) {
                foreach (self::ADDRESS_COLUMNS as $part => $length) {
                    $this->string($table, "{$group}_{$part}", $length);
                }
            }

            if (! Schema::hasColumn(self::TABLE, 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn(self::TABLE, 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Older schemas used user_id instead of owner_id.
        if ($addedOwner && Schema::hasColumn(self::TABLE, 'user_id')) {
            DB::table(self::TABLE)->update([
                'owner_id' => DB::raw('user_id'),
            ]);
        }
    }

    public function down(): void
    {
        // Drop foreign keys first, then their columns.
        $existingForeignKeys = collect(Schema::getForeignKeys(self::TABLE))
            ->flatMap(fn (array $fk) => $fk['columns'])
            ->all();

        Schema::table(self::TABLE, function (Blueprint $table) use ($existingForeignKeys) {
            foreach (self::FOREIGN_COLUMNS as $column) {
                if (! Schema::hasColumn(self::TABLE, $column)) {
                    continue;
                }

                if (in_array($column, $existingForeignKeys, true)) {
                    $table->dropConstrainedForeignId($column);
                } else {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (Schema::hasColumn(self::TABLE, 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $columns = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'birthday',
                'description',
                ...array_keys(self::OWNED_STRINGS),
            ];

            foreach (['present', 'permanent'] as $group) {
                foreach (array_keys(self::ADDRESS_COLUMNS) as $part) {
                    $columns[] = "{$group}_{$part}";
                }
            }

            $existing = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    /**
     * Only for a table that already existed: make sure `id` is
     * BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, touching it only if needed.
     */
    private function normalizeExistingId(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'id')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE AS type, EXTRA AS extra
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = "id"',
            [self::TABLE]
        );

        $isCorrect = $column !== null
            && str_contains(strtolower((string) $column->type), 'bigint')
            && str_contains(strtolower((string) $column->type), 'unsigned')
            && str_contains(strtolower((string) $column->extra), 'auto_increment');

        if ($isCorrect) {
            return;
        }

        // MySQL refuses to change a column that other tables reference
        // (error 1833), even with FOREIGN_KEY_CHECKS=0. So: drop those
        // foreign keys, change the column, then add the keys back.
        $detached = $this->detachReferencingForeignKeys();

        $hasPrimary = ! empty(DB::select(
            'SHOW INDEX FROM `contacts` WHERE Key_name = "PRIMARY"'
        ));

        if (! $hasPrimary) {
            DB::statement('ALTER TABLE `contacts` ADD PRIMARY KEY (`id`)');
        }

        DB::statement(
            'ALTER TABLE `contacts` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );

        $this->reattachForeignKeys($detached);
    }

    /**
     * Drop every foreign key (in any table) that references contacts.id and
     * return what is needed to put them back.
     *
     * @return array<int, object>
     */
    private function detachReferencingForeignKeys(): array
    {
        $foreignKeys = DB::select(
            "SELECT k.TABLE_NAME AS tbl,
                    k.CONSTRAINT_NAME AS name,
                    k.COLUMN_NAME AS col,
                    rc.DELETE_RULE AS on_delete,
                    rc.UPDATE_RULE AS on_update,
                    c.IS_NULLABLE AS nullable
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              AND rc.TABLE_NAME = k.TABLE_NAME
             JOIN information_schema.COLUMNS c
               ON c.TABLE_SCHEMA = k.TABLE_SCHEMA
              AND c.TABLE_NAME = k.TABLE_NAME
              AND c.COLUMN_NAME = k.COLUMN_NAME
             WHERE k.TABLE_SCHEMA = DATABASE()
               AND k.REFERENCED_TABLE_NAME = ?
               AND k.REFERENCED_COLUMN_NAME = 'id'",
            [self::TABLE]
        );

        foreach ($foreignKeys as $fk) {
            DB::statement(
                "ALTER TABLE `{$fk->tbl}` DROP FOREIGN KEY `{$fk->name}`"
            );
        }

        return $foreignKeys;
    }

    /**
     * Convert each detached column to BIGINT UNSIGNED (keeping NULL / NOT NULL)
     * and restore its foreign key to contacts.id with the original rules.
     *
     * @param array<int, object> $foreignKeys
     */
    private function reattachForeignKeys(array $foreignKeys): void
    {
        foreach ($foreignKeys as $fk) {
            $null = strtoupper((string) $fk->nullable) === 'YES' ? 'NULL' : 'NOT NULL';

            DB::statement(
                "ALTER TABLE `{$fk->tbl}` MODIFY `{$fk->col}` BIGINT UNSIGNED {$null}"
            );

            DB::statement(
                "ALTER TABLE `{$fk->tbl}`
                 ADD CONSTRAINT `{$fk->name}`
                 FOREIGN KEY (`{$fk->col}`) REFERENCES `contacts` (`id`)
                 ON DELETE {$fk->on_delete} ON UPDATE {$fk->on_update}"
            );
        }
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
            $table->foreignId($column)
                ->nullable()
                ->constrained($references)
                ->nullOnDelete();
        }
    }
};