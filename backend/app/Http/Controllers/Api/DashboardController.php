<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $admin = $request->user()->can('shops.manage');
        $shops = Shop::query()->when(! $admin, fn ($q) => $q->where('owner_id', $request->user()->id));
        $listings = ShopProduct::whereIn('shop_id', (clone $shops)->select('id'));

        return response()->json([
            'stats' => ['shops' => (clone $shops)->count(), 'products' => Product::count(), 'listings' => (clone $listings)->count(),
                'active_listings' => (clone $listings)->where('is_active', true)->whereHas('shop', fn ($q) => $q->where('is_active', true))->count(),
                'owners' => $admin ? User::role('shop_owner')->count() : null],
            'recent_products' => Product::with('creator:id,name')->latest()->limit(5)->get(),
            'shops' => $shops->with('owner:id,name')->withCount('listings')->latest()->limit(5)->get(),
        ]);
    }
}
