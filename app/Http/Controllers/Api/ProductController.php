<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $products = Product::where('user_id', $userId)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'products' => ProductResource::collection($products),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'category' => $request->input('category'),
            'image_url' => $request->input('image_url'),
        ]);

        return response()->json([
            'product' => new ProductResource($product),
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $product->update($request->validated());

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        if ($product->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $product->delete();

        return response()->json(['status' => 'deleted']);
    }
}
