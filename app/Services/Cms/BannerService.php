<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\BannerSlide;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BannerService
{
    private const FIELDS = ['name', 'location_label', 'title_lines', 'text', 'facts', 'cta_label', 'cta_url', 'gradient', 'is_active', 'starts_at', 'ends_at'];

    public function __construct(private readonly MediaStorage $media) {}

    public function list(): Collection
    {
        return BannerSlide::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    public function store(array $data, ?UploadedFile $image = null): BannerSlide
    {
        $slide = new BannerSlide(Arr::only($data, self::FIELDS));
        $slide->sort_order = (int) BannerSlide::query()->max('sort_order') + 1;

        if ($image) {
            $slide->image = $this->media->store($image, 'cms/banners');
        }

        $slide->save();

        return $slide;
    }

    public function update(BannerSlide $slide, array $data, ?UploadedFile $image = null, bool $removeImage = false): BannerSlide
    {
        $slide->fill(Arr::only($data, self::FIELDS));

        if ($image) {
            $this->media->delete($slide->image);
            $slide->image = $this->media->store($image, 'cms/banners');
        } elseif ($removeImage) {
            $this->media->delete($slide->image);
            $slide->image = null;
        }

        $slide->save();

        return $slide;
    }

    public function toggle(BannerSlide $slide): BannerSlide
    {
        $slide->update(['is_active' => ! $slide->is_active]);

        return $slide;
    }

    public function delete(BannerSlide $slide): void
    {
        $this->media->delete($slide->image);
        $slide->delete();
    }

    /** @param array<int,int> $ids every slide id in the new order */
    public function reorder(array $ids): void
    {
        $existing = BannerSlide::query()->pluck('id')->all();

        if (count($ids) !== count($existing) || array_diff($ids, $existing)) {
            throw ValidationException::withMessages(['ids' => 'The list does not match the current slides.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                BannerSlide::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        SiteContentCache::flush();
    }
}
