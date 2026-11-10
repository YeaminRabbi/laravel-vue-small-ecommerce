<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Product::with('creator:id,name')->withCount('listings')->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->string('search')->toString(), 0, 100).'%'))->latest()->paginate(50));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        return response()->json(Product::create([...$request->validated(), 'created_by' => $request->user()->id]), 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->can('catalog.manage') || $product->created_by === $request->user()->id, 403);
        $product->update($request->validated());

        return response()->json($product);
    }

    public function destroy(Request $request, Product $product): Response
    {
        abort_unless($request->user()->can('catalog.manage') || $product->created_by === $request->user()->id, 403);
        abort_if($product->listings()->exists(), 422, 'Remove this product from all shops before deleting it.');
        $product->delete();

        return response()->noContent();
    }
}
