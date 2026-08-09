<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier:id,name', 'user:id,name', 'items.product:id,name,sku'])
            ->latest();

        if ($request->filled('from')) {
            $query->whereDate('purchase_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('purchase_date', '<=', $request->to);
        }

        $purchases = $query->paginate((int) $request->get('per_page', 15));

        return response()->json($purchases);
    }

    public function store(PurchaseRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $total = 0;

            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_cost'];
            }

            $purchase = Purchase::create([
                'invoice_number' => 'PUR-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'supplier_id' => $request->supplier_id,
                'user_id' => $request->user()->id,
                'total_amount' => $total,
                'purchase_date' => $request->input('purchase_date', today()),
                'notes' => $request->input('notes'),
            ]);

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $batch = $product->batches()->firstOrCreate(
                    ['batch_number' => $item['batch_number']],
                    ['expiry_date' => $item['expiry_date']]
                );

                if (! $batch->wasRecentlyCreated) {
                    $batch->update(['expiry_date' => $item['expiry_date']]);
                }

                $batch->increment('quantity', $item['quantity']);

                $batch->update(['cost_price' => $item['unit_cost']]);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'batch_id' => $batch->id,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'total_cost' => $item['quantity'] * $item['unit_cost'],
                ]);
            }

            $purchase->load(['supplier', 'user:id,name', 'items.product']);

            return response()->json(['message' => 'Purchase recorded.', 'purchase' => $purchase], 201);
        });
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'user:id,name', 'items.product', 'items.batch']);

        return response()->json($purchase);
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                if ($item->batch) {
                    $item->batch->decrement('quantity', $item->quantity);
                }
            }

            $purchase->delete();
        });

        return response()->json(['message' => 'Purchase deleted, stock reverted.']);
    }
}