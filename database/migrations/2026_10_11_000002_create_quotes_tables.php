<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->string('quote_number', 30)->unique();
            $table->string('subject', 150)->index();
            $table->string('status', 20)->default('draft')->index();

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->date('issue_date')->index();
            $table->date('valid_until')->nullable()->index();
            $table->string('currency', 3)->default('USD');

            // Calculated by QuoteCalculator on every save; never trusted from the client.
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->text('terms')->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'valid_until']);
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            // The line keeps its own name / price: later edits to the product never rewrite an issued quote.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('unit', 40)->nullable();
            $table->decimal('quantity', 15, 3)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('line_total', 15, 2)->default(0); // after discount, before tax
            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
