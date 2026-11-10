<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListingRequest;
use App\Models\Shop;
use App\Models\ShopProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ListingController extends Controller
{
    private function authorizeShop(Request $request, Shop $shop): void
    {
        abort_unless($request->user()->can('shops.manage') || $shop->owner_id === $request->user()->id, 403);
    }

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        return response()->json($shop->listings()->with('product:id,name,sku')->latest()->paginate(50));
    }

    public function store(ListingRequest $request, Shop $shop): JsonResponse
    {
        return response()->json($shop->listings()->create($request->validated()), 201);
    }

    public function update(ListingRequest $request, Shop $shop, ShopProduct $listing): JsonResponse
    {
        abort_unless($listing->shop_id === $shop->id, 404);
        $listing->update($request->validated());

        return response()->json($listing);
    }

    public function destroy(Request $request, Shop $shop, ShopProduct $listing): Response
    {
        $this->authorizeShop($request, $shop);
        abort_unless($listing->shop_id === $shop->id, 404);
        $listing->delete();

        return response()->noContent();
    }
}
