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

            $this->applyItems($purchase, $request->items);

            $purchase->load(['supplier', 'user:id,name', 'items.product']);

            return response()->json(['message' => 'Purchase recorded.', 'purchase' => $purchase], 201);
        });
    }

    public function update(PurchaseRequest $request, Purchase $purchase): JsonResponse
    {
        return DB::transaction(function () use ($request, $purchase) {
            $purchase->load('items.batch');

            foreach ($purchase->items as $item) {
                if (! $item->batch) {
                    continue;
                }

                if ($item->batch->quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Cannot edit this purchase: batch {$item->batch->batch_number} already has stock sold against it. Delete the purchase instead.",
                    ]);
                }

                $item->batch->decrement('quantity', $item->quantity);
            }

            $purchase->items()->delete();

            $total = 0;

            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_cost'];
            }

            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'total_amount' => $total,
                'purchase_date' => $request->input('purchase_date', today()),
                'notes' => $request->input('notes'),
            ]);

            $this->applyItems($purchase, $request->items);

            $purchase->load(['supplier', 'user:id,name', 'items.product', 'items.batch']);

            return response()->json(['message' => 'Purchase updated.', 'purchase' => $purchase]);
        });
    }

    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'user:id,name', 'items.product', 'items.batch']);

        return response()->json($purchase);
    }

    private function applyItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);
            $enteredQty = (int) $item['quantity'];
            $enteredCost = (float) $item['unit_cost'];
            $perPack = (int) ($item['items_per_pack'] ?? 0);
            $fields = [];

            if ($perPack > 1) {
                $unitCost = round($enteredCost / $perPack, 2);
                $batchQty = $enteredQty * $perPack;
                $fields['items_per_pack'] = $perPack;
            } else {
                $unitCost = $enteredCost;
                $batchQty = $enteredQty;
            }

            $fields['cost_price'] = $unitCost;
            if (isset($item['sale_price']) && (float) $item['sale_price'] > 0) {
                $salePerItem = $perPack > 1
                    ? round((float) $item['sale_price'] / $perPack, 2)
                    : round((float) $item['sale_price'], 2);
                $fields['price'] = $salePerItem;
            }
            $product->update($fields);

            $batch = $product->batches()->firstOrCreate(
                ['batch_number' => $item['batch_number']],
                ['expiry_date' => $item['expiry_date']]
            );

            if (! $batch->wasRecentlyCreated) {
                $batch->update(['expiry_date' => $item['expiry_date']]);
            }

            $batch->increment('quantity', $batchQty);

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'quantity' => $batchQty,
                'unit_cost' => $unitCost,
                'total_cost' => $batchQty * $unitCost,
            ]);
        }
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                if (! $item->batch) {
                    continue;
                }

                if ($item->batch->quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Cannot delete this purchase: stock from batch {$item->batch->batch_number} has already been sold. Refund those sales first.",
                    ]);
                }

                $item->batch->decrement('quantity', $item->quantity);
            }

            $purchase->delete();
        });

        return response()->json(['message' => 'Purchase deleted, stock reverted.']);
    }
}