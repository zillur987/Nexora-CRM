<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ReorderMenuRequest;
use App\Http\Requests\Cms\SaveMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Services\Cms\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MenuItemController extends Controller
{
    public function __construct(private readonly MenuService $service) {}

    public function index()
    {
        return MenuItemResource::collection($this->service->tree());
    }

    public function show(MenuItem $menuItem): MenuItemResource
    {
        return MenuItemResource::make($menuItem->load('children.children'));
    }

    public function store(SaveMenuItemRequest $request): JsonResponse
    {
        $item = $this->service->store($request->validated(), $request->file('feature_image'));

        return MenuItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(SaveMenuItemRequest $request, MenuItem $menuItem): MenuItemResource
    {
        return MenuItemResource::make($this->service->update(
            $menuItem,
            $request->validated(),
            $request->file('feature_image'),
            $request->boolean('remove_feature_image'),
        ));
    }

    public function toggle(MenuItem $menuItem): MenuItemResource
    {
        return MenuItemResource::make($this->service->toggle($menuItem));
    }

    public function reorder(ReorderMenuRequest $request): Response
    {
        $this->service->reorder($request->validated('parent_id'), $request->validated('ids'));

        return response()->noContent();
    }

    public function destroy(MenuItem $menuItem): Response
    {
        $this->service->delete($menuItem);

        return response()->noContent();
    }
}
