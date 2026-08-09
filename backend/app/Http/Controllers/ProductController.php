<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->withSum('batches as stock', 'quantity')
            ->withCount([
                'batches as active_batches' => fn ($q) => $q->where('quantity', '>', 0),
            ]);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('generic_name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%")
                    ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->latest()->paginate((int) ($request->get('per_page', 15)));

        return response()->json($products);
    }

    public function all(): JsonResponse
    {
        $products = Product::withSum('batches as stock', 'quantity')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json(['message' => 'Product created.', 'product' => $product->load('batches')], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('batches');

        $product->stock = $product->stockTotal();

        return response()->json($product);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json(['message' => 'Product updated.', 'product' => $product->fresh('batches')]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    public function addBatch(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'batch_number' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expiry_date' => ['required', 'date', 'after:today'],
        ]);

        $batch = $product->batches()->create($validated);

        return response()->json(['message' => 'Batch added.', 'batch' => $batch->load('product')], 201);
    }

    public function updateBatch(Request $request, Batch $batch): JsonResponse
    {
        $validated = $request->validate([
            'batch_number' => ['sometimes', 'string', 'max:50'],
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'expiry_date' => ['sometimes', 'date'],
        ]);

        $batch->update($validated);

        return response()->json(['message' => 'Batch updated.', 'batch' => $batch->fresh('product')]);
    }

    public function destroyBatch(Batch $batch): JsonResponse
    {
        $batch->delete();

        return response()->json(['message' => 'Batch deleted.']);
    }
}