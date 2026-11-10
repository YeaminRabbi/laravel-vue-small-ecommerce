<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function shops(): JsonResponse
    {
        return response()->json(Shop::query()
            ->where('is_active', true)
            ->whereHas('listings', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']));
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(ShopProduct::query()->where('is_active', true)->whereHas('shop', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->with('shop:id,name,slug')->select(['id', 'shop_id', 'name', 'description', 'price', 'thumbnail'])->latest()->paginate(24));
    }
}
