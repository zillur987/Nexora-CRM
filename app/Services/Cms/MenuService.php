<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MenuService
{
    private const FIELDS = [
        'link' => ['label', 'url', 'target', 'is_active'],
        'mega' => ['label', 'url', 'target', 'meta_text', 'feature_label', 'feature_title', 'feature_sub', 'feature_url', 'is_active'],
        'column' => ['label', 'is_active'],
        'item' => ['label', 'description', 'url', 'target', 'is_active'],
    ];

    public function __construct(private readonly MediaStorage $media) {}

    /** Whole header tree, inactive items included (admin view). */
    public function tree(): Collection
    {
        return MenuItem::query()->header()->whereNull('parent_id')
            ->with('children.children')
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function store(array $data, ?UploadedFile $featureImage = null): MenuItem
    {
        $type = MenuItemType::from($data['type']);
        $parentId = $data['parent_id'] ?? null;
        $this->assertHierarchy($type, $parentId);

        return DB::transaction(function () use ($type, $parentId, $data, $featureImage): MenuItem {
            $item = new MenuItem(Arr::only($data, self::FIELDS[$type->value]));
            $item->type = $type;
            $item->location = 'header';
            $item->parent_id = $parentId;
            $item->sort_order = (int) MenuItem::query()->header()->where('parent_id', $parentId)->max('sort_order') + 1;

            if ($type === MenuItemType::Mega && $featureImage) {
                $item->feature_image = $this->media->store($featureImage, 'cms/menu');
            }

            $item->save();

            return $item->load('children.children');
        });
    }

    public function update(MenuItem $item, array $data, ?UploadedFile $featureImage = null, bool $removeImage = false): MenuItem
    {
        $item->fill(Arr::only($data, self::FIELDS[$item->type->value]));

        if ($item->type === MenuItemType::Mega) {
            if ($featureImage) {
                $this->media->delete($item->feature_image);
                $item->feature_image = $this->media->store($featureImage, 'cms/menu');
            } elseif ($removeImage) {
                $this->media->delete($item->feature_image);
                $item->feature_image = null;
            }
        }

        $item->save();

        return $item->load('children.children');
    }

    public function toggle(MenuItem $item): MenuItem
    {
        $item->update(['is_active' => ! $item->is_active]);

        return $item;
    }

    public function delete(MenuItem $item): void
    {
        $this->media->delete($item->feature_image);
        $item->delete();   // children are removed by the foreign key cascade
    }

    /** @param array<int,int> $ids sibling ids in their new order */
    public function reorder(?int $parentId, array $ids): void
    {
        $siblings = MenuItem::query()->header()->where('parent_id', $parentId)->pluck('id')->all();

        if (count($ids) !== count($siblings) || array_diff($ids, $siblings)) {
            throw ValidationException::withMessages(['ids' => 'The list does not match the items in this menu level.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                MenuItem::query()->whereKey($id)->update(['sort_order' => $position + 1]);   // bypasses model events on purpose
            }
        });

        SiteContentCache::flush();
    }

    private function assertHierarchy(MenuItemType $type, int|string $parentId = null)
    {
        $required = $type->parentType();

        if ($required === null) {
            if ($parentId !== null) {
                throw ValidationException::withMessages(['parent_id' => 'This item type lives at the top level.']);
            }

            return;
        }

        $parent = $parentId ? MenuItem::query()->find($parentId) : null;

        if (! $parent || $parent->type !== $required) {
            throw ValidationException::withMessages(['parent_id' => "A {$type->value} must be added inside a {$required->value}."]);
        }
    }
}
