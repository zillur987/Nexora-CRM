<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

final class SiteSettingService
{
    public const GROUPS = ['header', 'hero'];

    private const DEFAULTS = [
        'header' => [
            'brand_name' => 'Jamidar Landmark',
            'logo_image' => null,
            'search_label' => 'Search',
            'search_url' => '#search',
            'cta_label' => 'Get in touch',
            'cta_url' => '#contact',
            'phone' => null,
            'whatsapp' => null,
            'show_language_switch' => false,
            'languages' => [
                ['code' => 'en', 'label' => 'English', 'short' => 'EN'],
                ['code' => 'bn', 'label' => 'বাংলা', 'short' => 'BN'],
            ],
        ],
        'hero' => [
            'headline' => 'Find your next plot or home with Jamidar Landmark',
            'video' => null,
            'poster' => null,
            'show_search' => true,
        ],
    ];

    /** Upload fields per group. Each can be cleared with a matching `remove_<field>` flag. */
    private const FILES = [
        'header' => ['logo_image'],
        'hero' => ['video', 'poster'],
    ];

    public function __construct(private readonly MediaStorage $media) {}

    /** Stored values merged over the defaults (raw paths, no URLs). */
    public function raw(string $group): array
    {
        $stored = SiteSetting::query()->where('group', $group)->value('data') ?? [];

        return array_replace(self::DEFAULTS[$group], is_array($stored) ? $stored : []);
    }

    /** Same as raw() plus a `<field>_url` for every upload field (what the admin form and the public API need). */
    public function get(string $group): array
    {
        $data = $this->raw($group);

        foreach (self::FILES[$group] as $field) {
            $data["{$field}_url"] = MediaStorage::url($data[$field] ?? null);
        }

        return $data;
    }

    public function update(string $group, array $input): array
    {
        $current = $this->raw($group);
        $data = Arr::only($input, array_keys(self::DEFAULTS[$group]));

        foreach (self::FILES[$group] as $field) {
            $file = $input[$field] ?? null;

            if ($file instanceof UploadedFile) {
                $this->media->delete($current[$field] ?? null);
                $data[$field] = $this->media->store($file, "cms/{$group}");
            } elseif (! empty($input["remove_{$field}"])) {
                $this->media->delete($current[$field] ?? null);
                $data[$field] = null;
            } else {
                $data[$field] = $current[$field] ?? null;
            }
        }

        SiteSetting::query()->updateOrCreate(['group' => $group], ['data' => array_replace($current, $data)]);

        return $this->get($group);
    }
}
