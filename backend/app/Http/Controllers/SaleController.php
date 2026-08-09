<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer:id,name,phone', 'user:id,name', 'items.product:id,name,sku'])
            ->withCount('items')
            ->latest();

        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('invoice_number', 'like', "%{$request->search}%");
        }

        $sales = $query->paginate((int) $request->get('per_page', 15));

        return response()->json($sales);
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['customer', 'user:id,name', 'items.product', 'items.batch']);

        return response()->json($sale);
    }

    public function store(SaleRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $items = $request->input('items');
            $subtotal = 0;
            $totalCost = 0;
            $prepared = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    throw ValidationException::withMessages(["items.$product->id" => 'Quantity must be at least 1.']);
                }

                $remaining = $quantity;

                foreach ($product->batches()->where('expiry_date', '>', Carbon::today())->orderBy('expiry_date')->get() as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $available = max(0, (int) $batch->quantity);
                    if ($available <= 0) {
                        continue;
                    }

                    $take = min($available, $remaining);

                    $prepared[] = [
                        'product' => $product,
                        'batch' => $batch,
                        'quantity' => $take,
                        'unit_price' => (float) $item['unit_price'] ?? (float) $product->price,
                        'unit_cost' => (float) $product->cost_price,
                    ];

                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for: {$product->name} ({$remaining} short).",
                    ]);
                }
            }

            $subtotal = collect($prepared)->sum(fn ($p) => $p['quantity'] * $p['unit_price']);
            $totalCost = collect($prepared)->sum(fn ($p) => $p['quantity'] * $p['unit_cost']);
            $discountPercent = (float) $request->input('discount', 0);
            $discount = round($subtotal * min(100, max(0, $discountPercent)) / 100, 2);
            $taxRate = (float) $request->input('tax', 0);
            $tax = round($subtotal * $taxRate / 100, 2);
            $total = max(0, $subtotal - $discount + $tax);

            $customer = null;
            if ($request->filled('customer_id')) {
                $customer = Customer::find($request->customer_id);
            } elseif ($request->filled('customer_name')) {
                $customer = Customer::create([
                    'name' => $request->customer_name,
                    'phone' => $request->input('customer_phone'),
                ]);
            }

            $method = $request->input('payment_method', 'cash');
            $paid = round((float) $request->input('paid', $total), 2);
            $due = round(max(0, $total - $paid), 2);
            $advance = round(max(0, $paid - $total), 2);

            if ($due > 0 && ! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Partial or credit payment requires a customer (a credit balance must be attached to someone).',
                ]);
            }

            if ($due > 0 && $method === 'cash') {
                $method = 'credit';
            }

            if ($due > 0) {
                $customer->increment('credit', $due);
            } elseif ($advance > 0 && $customer) {
                $customer->decrement('credit', $advance);
            }

            $seq = Sale::where('invoice_number', 'like', date('dmy') . '-%')->count() + 1;
            $invoiceNumber = date('dmy') . '-' . $seq;

            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $customer?->id,
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'paid' => $paid,
                'due' => $due,
                'payment_method' => $method,
                'status' => 'completed',
            ]);

            foreach ($prepared as $p) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $p['product']->id,
                    'batch_id' => $p['batch']->id,
                    'quantity' => $p['quantity'],
                    'unit_price' => $p['unit_price'],
                    'unit_cost' => $p['unit_cost'],
                    'total' => $p['quantity'] * $p['unit_price'],
                ]);

                $p['batch']->decrement('quantity', $p['quantity']);
            }

            $sale->load(['customer', 'user:id,name', 'items.product:id,name,price', 'items.batch:id,batch_number']);

            return response()->json(['message' => 'Sale completed.', 'sale' => $sale], 201);
        });
    }

    public function lookup(string $invoice): JsonResponse
    {
        $sale = Sale::with(['customer:id,name', 'user:id,name', 'items.product:id,name,price'])
            ->where('invoice_number', trim($invoice))
            ->first();

        if (! $sale) {
            return response()->json(['message' => 'No sale found with that receipt number.'], 404);
        }

        return response()->json($sale);
    }

    public function refund(Sale $sale): JsonResponse
    {
        return DB::transaction(function () use ($sale) {
            if ($sale->status === 'refunded') {
                return response()->json(['message' => 'Sale already refunded.'], 422);
            }

            foreach ($sale->items as $item) {
                if ($item->batch) {
                    $item->batch->increment('quantity', $item->quantity);
                }
            }

            if ($sale->due > 0 && $sale->customer) {
                $sale->customer->decrement('credit', $sale->due);
            }

            $sale->update(['status' => 'refunded']);

            return response()->json(['message' => 'Sale refunded.', 'sale' => $sale->fresh('items')]);
        });
    }
}