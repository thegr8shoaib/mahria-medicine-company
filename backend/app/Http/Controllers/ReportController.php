<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function scopeByUser($query, Request $request)
    {
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    private function scopeSaleItemsByUser($query, Request $request)
    {
        if (! $request->user()->isAdmin()) {
            $query->whereHas('sale', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        return $query;
    }

    public function dashboard(Request $request): JsonResponse
    {
        $today = Carbon::today();

        $todayQ = $this->scopeByUser(Sale::whereDate('created_at', $today)->where('status', 'completed'), $request);
        $todaySales = $todayQ->selectRaw('COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(paid), 0) as paid')
            ->first();

        $todayProfit = $this->scopeSaleItemsByUser(SaleItem::whereHas('sale', fn ($q) => $q->whereDate('created_at', $today)->where('status', 'completed')), $request)
            ->selectRaw('COALESCE(SUM((unit_price - unit_cost) * quantity), 0) as profit')
            ->first()->profit;

        $lowStock = Product::withSum('batches as stock', 'quantity')
            ->get()
            ->filter(fn ($p) => $p->stock <= $p->low_stock_alert)
            ->values();

        $expiring = Batch::with('product:id,name,sku')
            ->where('quantity', '>', 0)
            ->whereBetween('expiry_date', [$today, $today->copy()->addDays(90)])
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'today' => [
                'revenue' => round((float) $todaySales['revenue'], 2),
                'profit' => round((float) $todayProfit, 2),
                'sales_count' => $this->scopeByUser(Sale::whereDate('created_at', $today)->where('status', 'completed'), $request)->count(),
            ],
            'month' => [
                'revenue' => round((float) $this->scopeByUser(Sale::whereMonth('created_at', $today->month)->whereYear('created_at', $today->year)->where('status', 'completed'), $request)->sum('total'), 2),
                'purchases' => round((float) Purchase::whereMonth('purchase_date', $today->month)->whereYear('purchase_date', $today->year)->sum('total_amount'), 2),
            ],
            'inventory' => [
                'products' => Product::count(),
                'total_stock' => (int) Batch::sum('quantity'),
                'low_stock' => $lowStock->count(),
                'expiring' => $expiring->count(),
                'low_stock_products' => $lowStock->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'stock' => $p->stock,
                ]),
                'expiring_batches' => $expiring->map(fn ($b) => [
                    'id' => $b->id,
                    'batch_number' => $b->batch_number,
                    'product' => $b->product?->name,
                    'quantity' => $b->quantity,
                    'expiry_date' => $b->expiry_date->format('Y-m-d'),
                    'days_left' => (int) $today->diffInDays($b->expiry_date, false),
                ]),
            ],
            'recent_sales' => $this->scopeByUser(Sale::with(['customer:id,name', 'items.product:id,name']), $request)
                ->latest()->take(10)->get(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $range = $request->input('range', '7');
        $days = (int) $range;
        $from = Carbon::today()->subDays($days - 1);

        $sales = $this->scopeByUser(Sale::where('status', 'completed'), $request)
            ->whereDate('created_at', '>=', $from)
            ->get(['created_at', 'total', 'discount']);

        $daily = collect(range(0, $days - 1))->map(function ($i) use ($from, $sales) {
            $day = $from->copy()->addDays($i);
            $daySales = $sales->filter(fn ($s) => $s->created_at->isSameDay($day));

            return [
                'date' => $day->format('Y-m-d'),
                'revenue' => round($daySales->sum('total'), 2),
                'sales_count' => $daySales->count(),
            ];
        });

        $cashiers = $this->scopeByUser(Sale::where('status', 'completed'), $request)
            ->whereDate('created_at', '>=', $from)
            ->with('user:id,name')
            ->get(['user_id', 'total', 'paid', 'due'])
            ->groupBy('user_id')
            ->map(fn ($group) => [
                'cashier' => $group->first()->user?->name ?? 'Unknown',
                'sales' => $group->count(),
                'revenue' => round($group->sum('total'), 2),
                'collected' => round($group->sum('paid'), 2),
                'credit' => round($group->sum('due'), 2),
            ])
            ->values();

        return response()->json([
            'daily' => $daily,
            'totals' => [
                'revenue' => round($sales->sum('total'), 2),
                'discounts' => round($sales->sum('discount'), 2),
                'sales_count' => $sales->count(),
            ],
            'cashiers' => $cashiers,
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->get('days', 30));

        $products = Product::withSum(
            ['saleItems as qty' => function ($q) use ($request, $days) {
                $q->whereHas('sale', function ($s) use ($request, $days) {
                    $s->whereDate('created_at', '>=', Carbon::today()->subDays($days)->startOfDay())
                        ->when(! $request->user()->isAdmin(), fn ($w) => $w->where('user_id', $request->user()->id));
                });
            }],
            'quantity'
        )
            ->get(['id', 'name', 'sku'])
            ->filter(fn ($p) => $p->qty > 0)
            ->sortByDesc('qty')
            ->take(10)
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'qty_sold' => (int) $p->qty,
            ]);

        return response()->json($products);
    }

    public function salesByDate(Request $request): JsonResponse
    {
        $date = Carbon::parse($request->input('date', today()));

        $sales = $this->scopeByUser(Sale::with(['customer:id,name,phone', 'user:id,name']), $request)
            ->whereDate('created_at', $date)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'date' => $date->format('Y-m-d'),
            'sales' => $sales,
            'totals' => [
                'revenue' => round($sales->sum('total'), 2),
                'count' => $sales->count(),
            ],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['batches'])
            ->withSum('batches as stock', 'quantity')
            ->orderBy('name')
            ->get();

        $productsRows = $products->map(fn ($p) => [
            'Name' => $p->name,
            'Generic Name' => $p->generic_name,
            'SKU' => $p->sku,
            'Barcode' => $p->barcode,
            'Unit' => $p->unit,
            'Price' => round((float) $p->price, 2),
            'Cost Price' => round((float) $p->cost_price, 2),
            'Available Stock' => (int) $p->stock,
            'Low Stock Alert' => (int) $p->low_stock_alert,
            'Status' => $p->is_active ? 'Active' : 'Inactive',
        ]);

        $batchRows = $products->flatMap(fn ($p) => $p->batches->map(fn ($b) => [
            'Product' => $p->name,
            'SKU' => $p->sku,
            'Batch Number' => $b->batch_number,
            'Quantity' => (int) $b->quantity,
            'Expiry Date' => Carbon::parse($b->expiry_date)->format('Y-m-d'),
            'Days Left' => (int) Carbon::today()->diffInDays(Carbon::parse($b->expiry_date), false),
        ]));

        return response()->json([
            'products' => $productsRows,
            'batches' => $batchRows,
            'suppliers' => Supplier::orderBy('name')->get()
                ->map(fn ($s) => [
                    'Name' => $s->name,
                    'Phone' => $s->phone,
                    'Email' => $s->email,
                    'Address' => $s->address,
                ]),
            'customers' => Customer::withSum('sales as total_spent', 'total')
                ->withCount('sales')
                ->orderBy('name')
                ->get()
                ->map(fn ($c) => [
                    'Name' => $c->name,
                    'Phone' => $c->phone,
                    'Email' => $c->email,
                    'Address' => $c->address,
                    'Purchases' => (int) ($c->sales_count ?? 0),
                    'Total Spent' => round((float) $c->total_spent, 2),
                ]),
            'sales' => $this->scopeByUser(Sale::with(['customer:id,name', 'user:id,name']), $request)
                ->withCount('items')
                ->latest()
                ->get()
                ->map(fn ($s) => [
                    'Invoice' => $s->invoice_number,
                    'Date' => $s->created_at->format('Y-m-d H:i'),
                    'Cashier' => $s->user?->name,
                    'Customer' => $s->customer?->name ?? 'Walk-in',
                    'Payment Method' => $s->payment_method,
                    'Items' => (int) $s->items_count,
                    'Subtotal' => round((float) $s->subtotal, 2),
                    'Discount' => round((float) $s->discount, 2),
                    'Total' => round((float) $s->total, 2),
                    'Paid' => round((float) $s->paid, 2),
                    'Status' => $s->status,
                ]),
        ]);
    }
}