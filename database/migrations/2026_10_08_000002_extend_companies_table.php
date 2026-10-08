<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Upgrades the original companies table (name/website/industry/phone/address JSON)
 * to the full company profile. The free-text `industry` column becomes a lookup FK
 * (existing values are carried over) and the `address` JSON becomes flat, queryable
 * billing_* / shipping_* columns.
 */
return new class extends Migration
{
    private const ADDRESS_COLUMNS = ['street' => 255, 'city' => 100, 'zip' => 20, 'state' => 100, 'country' => 100];

    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('industry_id')->nullable()->constrained('industries')->nullOnDelete();
            $table->foreignId('company_type_id')->nullable()->constrained('company_types')->nullOnDelete();

            $table->string('size', 20)->nullable();
            $table->decimal('annual_revenue', 16, 2)->nullable();
            $table->char('currency', 3)->default('USD');

            $table->string('email', 254)->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();

            foreach (['billing', 'shipping'] as $group) {
                foreach (self::ADDRESS_COLUMNS as $part => $length) {
                    $table->string("{$group}_{$part}", $length)->nullable();
                }
            }

            $table->text('description')->nullable();

            $table->index('size');
            $table->index('billing_country');
        });

        $this->carryOverIndustries();

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['industry', 'address']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('industry')->nullable();
            $table->json('address')->nullable();
        });

        DB::table('industries')->orderBy('id')->each(
            fn (object $industry) => DB::table('companies')
                ->where('industry_id', $industry->id)
                ->update(['industry' => $industry->name]),
        );

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['industry_id']);
            $table->dropForeign(['company_type_id']);
            $table->dropIndex(['size']);
            $table->dropIndex(['billing_country']);

            $columns = [
                'industry_id', 'company_type_id', 'size', 'annual_revenue', 'currency',
                'email', 'linkedin', 'twitter', 'instagram', 'facebook', 'description',
            ];
            foreach (['billing', 'shipping'] as $group) {
                foreach (array_keys(self::ADDRESS_COLUMNS) as $part) {
                    $columns[] = "{$group}_{$part}";
                }
            }

            $table->dropColumn($columns);
        });
    }

    /** Turns each distinct free-text industry into a lookup row and links the companies to it. */
    private function carryOverIndustries(): void
    {
        $names = DB::table('companies')
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->distinct()
            ->pluck('industry');

        foreach ($names as $original) {
            $name = Str::limit(trim($original), 120, '');

            $id = DB::table('industries')->where('name', $name)->value('id')
                ?? DB::table('industries')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('companies')->where('industry', $original)->update(['industry_id' => $id]);
        }
    }
};
