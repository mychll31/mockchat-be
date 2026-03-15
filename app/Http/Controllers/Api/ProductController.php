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
        $query = Product::query();

        if ($request->has('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'products' => ProductResource::collection($products),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create([
            'user_id' => $request->input('user_id', 1),
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

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'product' => new ProductResource($product),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['status' => 'deleted']);
    }
}
