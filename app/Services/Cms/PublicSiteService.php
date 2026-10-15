<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\MenuItemType;
use App\Models\BannerSlide;
use App\Models\MenuItem;

/** Builds the lean, cached payloads the public Vue site consumes. Only active content is exposed. */
final class PublicSiteService
{
    public function __construct(private readonly SiteSettingService $settings) {}

    public function header(): array
    {
        return SiteContentCache::remember('header', function (): array {
            $s = $this->settings->get('header');

            $items = MenuItem::query()->header()->active()->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->active(), 'children.children' => fn ($q) => $q->active()])
                ->orderBy('sort_order')->orderBy('id')
                ->get();

            return [
                'brand' => ['name' => $s['brand_name'], 'logo_url' => $s['logo_image_url']],
                'menu' => $items->map(fn (MenuItem $i) => $this->menuNode($i))->values()->all(),
                'actions' => [
                    'search_label' => $s['search_label'],
                    'search_url' => $s['search_url'],
                    'cta_label' => $s['cta_label'],
                    'cta_url' => $s['cta_url'],
                    'phone' => $s['phone'],
                    'whatsapp' => $s['whatsapp'],
                ],
                'languages' => $s['show_language_switch'] ? $s['languages'] : [],
            ];
        });
    }

    public function banner(): array
    {
        return SiteContentCache::remember('banner', function (): array {
            $h = $this->settings->get('hero');

            return [
                'hero' => [
                    'headline' => $h['headline'],
                    'video_url' => $h['video_url'],
                    'poster_url' => $h['poster_url'],
                    'show_search' => (bool) $h['show_search'],
                ],
                'slides' => BannerSlide::query()->published()->orderBy('sort_order')->orderBy('id')->get()
                    ->map(fn (BannerSlide $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'gradient' => $s->gradient,
                        'image_url' => MediaStorage::url($s->image),
                        'location' => $s->location_label,
                        'lines' => array_values($s->title_lines ?? []),
                        'text' => $s->text,
                        'facts' => array_values($s->facts ?? []),
                        'cta_label' => $s->cta_label,
                        'cta_url' => $s->cta_url,
                    ])->values()->all(),
            ];
        });
    }

    private function menuNode(MenuItem $item): array
    {
        $base = ['id' => $item->id, 'label' => $item->label, 'url' => $item->url ?: '#', 'target' => $item->target];

        if ($item->type !== MenuItemType::Mega) {
            return $base + ['type' => 'link'];
        }

        $columns = $item->children
            ->map(fn (MenuItem $col) => [
                'heading' => $col->label,
                'links' => $col->children->map(fn (MenuItem $l) => [
                    'label' => $l->label, 'sub' => $l->description, 'url' => $l->url ?: '#', 'target' => $l->target,
                ])->values()->all(),
            ])
            ->filter(fn (array $col) => $col['links'] !== [])
            ->values()->all();

        // A mega menu with nothing inside degrades to a normal link instead of an empty panel.
        if ($columns === []) {
            return $base + ['type' => 'link'];
        }

        $hasFeature = $item->feature_title || $item->feature_image;

        return $base + [
            'type' => 'mega',
            'title' => $item->label,
            'meta' => $item->meta_text,
            'columns' => $columns,
            'feature' => $hasFeature ? [
                'label' => $item->feature_label,
                'title' => $item->feature_title,
                'sub' => $item->feature_sub,
                'url' => $item->feature_url ?: $item->url ?: '#',
                'image_url' => MediaStorage::url($item->feature_image),
            ] : null,
        ];
    }
}
