<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContactSource;
use App\Models\ContactStage;
use Illuminate\Database\Seeder;

/** Starter values for the Contact Source / Contact Stage dropdowns. Safe to run repeatedly. */
class ContactLookupSeeder extends Seeder
{
    private const SOURCES = [
        'Website', 'Referral', 'Social media', 'Email campaign', 'Cold call',
        'Event', 'Partner', 'Walk-in', 'Other',
    ];

    private const STAGES = [
        'Subscriber', 'Lead', 'Marketing qualified', 'Sales qualified',
        'Opportunity', 'Customer', 'Evangelist', 'Other',
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $name) {
            ContactSource::query()->firstOrCreate(['name' => $name]);
        }

        foreach (self::STAGES as $name) {
            ContactStage::query()->firstOrCreate(['name' => $name]);
        }
    }
}
