<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BannerSlide;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * SAMPLE content for Jamidar Landmark Limited so the site is not empty on day one.
 * Replace every text, number and image from the admin panel before launch.
 * Safe to re-run: it only seeds tables that are still empty.
 */
class CmsContentSeeder extends Seeder
{
    public function run(): void
    {
        SiteSetting::query()->firstOrCreate(['group' => 'header'], ['data' => [
            'brand_name' => 'Jamidar Landmark',
            'search_label' => 'Search',
            'search_url' => '#search',
            'cta_label' => 'Get in touch',
            'cta_url' => '#contact',
            'phone' => null,
            'whatsapp' => null,
            'show_language_switch' => false,
            'languages' => [['code' => 'en', 'label' => 'English', 'short' => 'EN'], ['code' => 'bn', 'label' => 'বাংলা', 'short' => 'BN']],
        ]]);

        SiteSetting::query()->firstOrCreate(['group' => 'hero'], ['data' => [
            'headline' => 'Find your next plot or home with Jamidar Landmark',
            'show_search' => true,
        ]]);

        if (! MenuItem::query()->exists()) {
            $this->seedMenu();
        }

        if (! BannerSlide::query()->exists()) {
            $this->seedSlides();
        }
    }

    private function seedMenu(): void
    {
        $order = 0;
        $link = fn (string $label, string $url) => MenuItem::create(['type' => 'link', 'label' => $label, 'url' => $url, 'sort_order' => ++$order]);

        $link('Home', '/');
        $link('About', '#about');

        $mega = function (string $label, string $url, string $meta, array $columns, array $feature) use (&$order): void {
            $m = MenuItem::create(['type' => 'mega', 'label' => $label, 'url' => $url, 'meta_text' => $meta, 'sort_order' => ++$order] + $feature);
            foreach ($columns as $c => [$heading, $links]) {
                $col = MenuItem::create(['type' => 'column', 'parent_id' => $m->id, 'label' => $heading, 'sort_order' => $c + 1]);
                foreach ($links as $i => [$name, $sub, $href]) {
                    MenuItem::create(['type' => 'item', 'parent_id' => $col->id, 'label' => $name, 'description' => $sub, 'url' => $href, 'sort_order' => $i + 1]);
                }
            }
        };

        $mega('Projects', '#projects', 'Plots, homes and resort properties', [
            ['Residential plots', [['Plot projects', 'Planned residential plots', '#projects'], ['Plot sizes', 'Choose what fits your family', '#projects']]],
            ['Homes & resort', [['Homes', 'Apartments and houses', '#projects'], ['Resort properties', 'Leisure and holiday living', '#resort']]],
            ['Status', [['Ongoing projects', 'Currently selling', '#projects'], ['Completed projects', 'Delivered by us', '#projects'], ['Upcoming projects', 'Coming soon', '#projects']]],
        ], ['feature_label' => 'Featured', 'feature_title' => 'Residential plots', 'feature_sub' => 'Build your home on a planned plot', 'feature_url' => '#projects']);

        $mega('Services', '#services', 'We stay with you from enquiry to handover', [
            ['Buy', [['Plot sales', 'Guidance from enquiry to registration', '#services'], ['Home sales', 'Find the right home', '#services']]],
            ['Support', [['Site visit', 'Book a visit with our team', '#contact'], ['Payment plans', 'Talk to us about instalments', '#services']]],
        ], ['feature_label' => 'Most requested', 'feature_title' => 'Book a site visit', 'feature_sub' => 'See the land before you decide', 'feature_url' => '#contact']);

        $link('Gallery', '#gallery');
    }

    private function seedSlides(): void
    {
        $slides = [
            ['Residential Plots', 'a', 'Planned communities around Dhaka', ['Residential plots', 'for your future home'],
                'Choose a plot, plan your home and build at your own pace with a developer that stays with you.',
                [['Residential', 'Type'], ['Near Dhaka', 'Location'], ['Instalment', 'Payment'], ['On request', 'Site visit']], 'Explore plots', '#projects'],
            ['Homes', 'c', 'Dhaka', ['Homes', 'made to live in'],
                'Apartments and houses designed for families, with the details that make everyday life easier.',
                [['Homes', 'Type'], ['Dhaka', 'Location'], ['Flexible', 'Payment'], ['Per schedule', 'Handover']], 'View homes', '#projects'],
            ['Resort', 'e', 'Resort & leisure', ['Resort living', 'away from the city'],
                'Resort properties for weekends, holidays and long-term investment, surrounded by open space.',
                [['Resort', 'Type'], ['Open space', 'Setting'], ['Leisure', 'Use'], ['Enquire', 'Availability']], 'Discover the resort', '#resort'],
            ['Purbachal Group', 'b', 'Jamidar Landmark Limited', ['A concern of', 'Purbachal Group'],
                'Real estate and resort development built on the experience and reputation of Purbachal Group.',
                [['Real estate', 'Business'], ['Resort', 'Business'], ['Dhaka', 'Base'], ['Talk to us', 'Next step']], 'About us', '#about'],
        ];

        foreach ($slides as $i => [$name, $gradient, $loc, $lines, $text, $facts, $cta, $url]) {
            BannerSlide::create([
                'name' => $name, 'gradient' => $gradient, 'location_label' => $loc, 'title_lines' => $lines, 'text' => $text,
                'facts' => array_map(fn ($f) => ['value' => $f[0], 'label' => $f[1]], $facts),
                'cta_label' => $cta, 'cta_url' => $url, 'sort_order' => $i + 1,
            ]);
        }
    }
}
