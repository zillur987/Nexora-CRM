<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ContactStatus;
use App\Enums\DealStage;
use App\Models\Contact;
use App\Models\Deal;

final class DashboardService
{
    public function __construct(private readonly DealService $deals) {}

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $order = array_map(fn (DealStage $s) => $s->value, DealStage::cases());

        $pipeline = collect($this->deals->pipeline())
            ->sortBy(fn (array $row) => array_search($row['stage'], $order, true))
            ->values();

        return [
            'contacts' => Contact::count(),
            'leads' => Contact::where('status', ContactStatus::Lead->value)->count(),
            'open_deals' => Deal::whereIn('stage', DealStage::openValues())->count(),
            'won_revenue' => Deal::where('stage', DealStage::Won->value)
                ->selectRaw('currency, SUM(amount) as total')
                ->groupBy('currency')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->currency => number_format((float) $r->total, 2)])
                ->all(),
            'pipeline' => $pipeline,
            'max_count' => max(1, (int) $pipeline->max('count')),
            'recent_deals' => Deal::with('contact')->latest()->limit(5)->get(),
            'recent_contacts' => Contact::latest()->limit(5)->get(),
        ];
    }
}
