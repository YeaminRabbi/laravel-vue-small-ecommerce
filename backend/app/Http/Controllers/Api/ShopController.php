<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShopRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Shop::with('owner:id,name,email')->withCount('listings')->when(! $request->user()->can('shops.manage'), fn ($q) => $q->where('owner_id', $request->user()->id))->latest()->paginate(50));
    }

    public function store(ShopRequest $request): JsonResponse
    {
        return response()->json(Shop::create($request->validated()), 201);
    }

    public function update(ShopRequest $request, Shop $shop): JsonResponse
    {
        $shop->update($request->validated());

        return response()->json($shop);
    }

    public function destroy(Shop $shop): Response
    {
        $shop->delete();

        return response()->noContent();
    }
}
