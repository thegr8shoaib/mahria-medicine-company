<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Batch;
use App\Models\Product;
use App\Support\Xlsx;
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

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('distributor_id')) {
            $query->whereHas('companyModel', fn ($q) => $q->where('distributor_id', $request->integer('distributor_id')));
        }

        $summary = (clone $query)
            ->select('id', 'price')
            ->withSum('batches as stock', 'quantity')
            ->get();

        $products = $query->latest()->paginate((int) ($request->get('per_page', 15)));

        $data = $products->toArray();
        $data['summary'] = [
            'total' => $summary->count(),
            'stock' => (int) $summary->sum('stock'),
            'value' => round($summary->sum(fn ($p) => $p->stock * (float) $p->price), 2),
        ];

        return response()->json($data);
    }

    public function all(Request $request): JsonResponse
    {
        $query = Product::withSum('batches as stock', 'quantity')
            ->with('companyModel:id,name,distributor_id')
            ->where('is_active', true);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('distributor_id')) {
            $query->whereHas('companyModel', fn ($q) => $q->where('distributor_id', $request->integer('distributor_id')));
        }

        $products = $query->orderBy('name')->get();

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

    public function exportExcel(): \Symfony\Component\HttpFoundation\Response
    {
        $products = Product::withSum('batches as stock', 'quantity')->orderBy('name')->get();

        $headers = ['Name', 'SKU', 'Barcode', 'Category', 'Company', 'Generic Name', 'Variant', 'Price', 'Cost Price', 'Unit', 'Low Stock Alert', 'Stock'];
        $rows = $products->map(fn (Product $p) => [
            $p->name,
            $p->sku,
            $p->barcode,
            $p->category,
            $p->company,
            $p->generic_name,
            $p->variants,
            (float) $p->price,
            (float) $p->cost_price,
            $p->unit,
            (int) $p->low_stock_alert,
            (int) $p->stock,
        ])->toArray();

        $tmp = Xlsx::export($headers, $rows);

        return response()->download($tmp, 'inventory-' . date('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $rows = in_array($ext, ['csv', 'txt'], true)
                ? Xlsx::parseCsv($file->getRealPath())
                : Xlsx::parseXlsx($file->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not read the file: ' . $e->getMessage()], 422);
        }

        $created = 0;
        $updated = 0;
        $batches = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $data = $this->mapRow($row);
            $name = trim($data['name'] ?? '');
            $sku = trim($data['sku'] ?? '');

            if (! $name && ! $sku) {
                $skipped++;
                continue;
            }

            $product = $sku
                ? Product::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first()
                : null;

            if (! $product) {
                $key = mb_strtolower($name);
                if ($key !== '') {
                    $product = Product::whereRaw('LOWER(name) = ?', [$key])->first();
                }
            }

            $fields = [];
            foreach (['name', 'company', 'generic_name', 'category', 'variants', 'sku', 'barcode', 'unit'] as $f) {
                if (! empty($data[$f])) {
                    $fields[$f] = (string) $data[$f];
                }
            }
            foreach (['price', 'cost_price', 'low_stock_alert'] as $f) {
                if ($data[$f] !== '' && $data[$f] !== null) {
                    $fields[$f] = max(0, (float) $data[$f]);
                }
            }

            if ($product) {
                if ($fields) {
                    $product->update($fields);
                    $updated++;
                }
            } else {
                if (empty($fields['name'])) {
                    $skipped++;
                    continue;
                }
                $fields['is_active'] = true;
                $product = Product::create($fields);
                $created++;
            }

            $batchNo = trim((string) ($data['batch_number'] ?? ''));
            $expiry = $this->parseDate($data['expiry_date'] ?? '');
            $qty = (float) ($data['batch_qty'] ?? '');

            if ($batchNo && $expiry && $qty > 0) {
                $batch = Batch::where('product_id', $product->id)
                    ->whereRaw('LOWER(batch_number) = ?', [mb_strtolower($batchNo)])
                    ->first();

                if ($batch) {
                    $batch->update(['quantity' => (int) $qty, 'expiry_date' => $expiry]);
                } else {
                    $product->batches()->create([
                        'batch_number' => $batchNo,
                        'quantity' => (int) $qty,
                        'expiry_date' => $expiry,
                    ]);
                }
                $batches++;
            }
        }

        return response()->json([
            'message' => "Import finished: {$created} created, {$updated} updated, {$batches} batches, {$skipped} skipped.",
            'created' => $created,
            'updated' => $updated,
            'batches' => $batches,
            'skipped' => $skipped,
        ]);
    }

    private function mapRow(array $row): array
    {
        $aliases = [
            'name' => ['name', 'product', 'productname', 'item'],
            'sku' => ['sku', 'item code', 'code', 'product code'],
            'barcode' => ['barcode', 'ean', 'upc'],
            'category' => ['category', 'type'],
            'company' => ['company', 'manufacturer', 'brand'],
            'generic_name' => ['generic name', 'generic'],
            'variants' => ['variant', 'variants', 'strength', 'pack size', 'pack'],
            'price' => ['price', 'sale price', 'selling price', 'retail price'],
            'cost_price' => ['cost price', 'cost', 'purchase price', 'buying price'],
            'unit' => ['unit', 'uom'],
            'low_stock_alert' => ['low stock alert', 'reorder level', 'low stock'],
            'batch_number' => ['batch number', 'batch no', 'batch', 'lot'],
            'expiry_date' => ['expiry date', 'exp', 'expiration', 'expiry'],
            'batch_qty' => ['batch qty', 'batch quantity', 'quantity', 'qty', 'stock'],
        ];

        $out = [];
        foreach ($row as $rawHeader => $value) {
            $h = mb_strtolower(trim((string) $rawHeader));
            foreach ($aliases as $field => $names) {
                if (in_array($h, $names, true)) {
                    $out[$field] = is_string($value) ? trim($value) : $value;
                    break;
                }
            }
        }

        return $out;
    }

    private function parseDate($v): ?string
    {
        if (is_numeric($v)) {
            $n = (int) $v;
            if ($n > 20000 && $n < 70000) {
                return date('Y-m-d', ($n - 25569) * 86400);
            }
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'Y/m/d'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $s);
            if ($d && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }
        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}