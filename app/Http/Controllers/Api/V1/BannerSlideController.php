<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ReorderBannerSlidesRequest;
use App\Http\Requests\Cms\SaveBannerSlideRequest;
use App\Http\Resources\BannerSlideResource;
use App\Models\BannerSlide;
use App\Services\Cms\BannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BannerSlideController extends Controller
{
    public function __construct(private readonly BannerService $service) {}

    public function index()
    {
        return BannerSlideResource::collection($this->service->list());
    }

    public function show(BannerSlide $bannerSlide): BannerSlideResource
    {
        return BannerSlideResource::make($bannerSlide);
    }

    public function store(SaveBannerSlideRequest $request): JsonResponse
    {
        $slide = $this->service->store($request->validated(), $request->file('image'));

        return BannerSlideResource::make($slide)->response()->setStatusCode(201);
    }

    public function update(SaveBannerSlideRequest $request, BannerSlide $bannerSlide): BannerSlideResource
    {
        return BannerSlideResource::make($this->service->update(
            $bannerSlide,
            $request->validated(),
            $request->file('image'),
            $request->boolean('remove_image'),
        ));
    }

    public function toggle(BannerSlide $bannerSlide): BannerSlideResource
    {
        return BannerSlideResource::make($this->service->toggle($bannerSlide));
    }

    public function reorder(ReorderBannerSlidesRequest $request): Response
    {
        $this->service->reorder($request->validated('ids'));

        return response()->noContent();
    }

    public function destroy(BannerSlide $bannerSlide): Response
    {
        $this->service->delete($bannerSlide);

        return response()->noContent();
    }
}
