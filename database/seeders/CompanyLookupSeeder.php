<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CompanyType;
use App\Models\Industry;
use Illuminate\Database\Seeder;

/** Starter values for the Industry / Company Type dropdowns. Safe to run repeatedly. */
class CompanyLookupSeeder extends Seeder
{
    private const INDUSTRIES = [
        'Technology', 'Finance & Banking', 'Healthcare', 'Manufacturing', 'Retail & E-commerce',
        'Education', 'Real Estate', 'Logistics & Transport', 'Telecommunications', 'Energy & Utilities',
        'Media & Entertainment', 'Hospitality & Travel', 'Agriculture', 'Construction', 'Non-profit', 'Other',
    ];

    private const TYPES = ['Prospect', 'Customer', 'Partner', 'Vendor', 'Reseller', 'Investor', 'Competitor', 'Other'];

    public function run(): void
    {
        foreach (self::INDUSTRIES as $name) {
            Industry::query()->firstOrCreate(['name' => $name]);
        }

        foreach (self::TYPES as $name) {
            CompanyType::query()->firstOrCreate(['name' => $name]);
        }
    }
}
