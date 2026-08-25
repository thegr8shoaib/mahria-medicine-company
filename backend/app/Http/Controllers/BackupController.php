<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function avatar(Request $request, string $file)
    {
        $file = basename($file);
        $path = storage_path('app/avatars/' . $file);

        if (! is_file($path)) {
            return response()->json(['message' => 'Avatar not found.'], 404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');

        return response()->file($path, ['Content-Type' => $mime]);
    }

    public function run(): JsonResponse
    {
        $backupDir = storage_path('app/backups');
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $stamp = date('Ymd-His');
        $folder = $backupDir . '/mahria-' . $stamp;

        try {
            $dbPath = database_path('database.sqlite');
            if (! is_file($dbPath)) {
                throw new \RuntimeException('database.sqlite not found.');
            }

            mkdir($folder, 0777, true);
            $pdo = new \PDO('sqlite:' . $dbPath);
            $target = $folder . '/database.sqlite';
            $pdo->exec("VACUUM INTO '" . str_replace("'", "''", $target) . "'");

            foreach (['avatars', 'QR.png', 'thermal-logo.png'] as $item) {
                $src = storage_path('app/' . $item);
                if (is_file($src)) {
                    copy($src, $folder . '/' . $item);
                } elseif (is_dir($src)) {
                    $this->copyDir($src, $folder . '/' . $item);
                }
            }

            $this->prune($backupDir);

            $dbCopy = $folder . '/database.sqlite';

            return response()->json([
                'message' => 'Backup created.',
                'file' => 'mahria-' . $stamp . '/database.sqlite',
                'size' => filesize($dbCopy),
                'folder' => basename($folder),
            ]);
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());

            return response()->json(['message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request, string $path)
    {
        if (! preg_match('#^mahria-[\w-]+/database\.sqlite$#', $path)) {
            return response()->json(['message' => 'Invalid backup file.'], 404);
        }

        $file = storage_path('app/backups/' . str_replace('/', DIRECTORY_SEPARATOR, $path));

        if (! is_file($file)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        return response()->download($file, 'mahria-backup-' . date('Ymd-His') . '.sqlite', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function index(): JsonResponse
    {
        $backupDir = storage_path('app/backups');

        $list = [];
        if (is_dir($backupDir)) {
            foreach (array_filter(glob($backupDir . '/mahria-*'), 'is_dir') as $folder) {
                $db = $folder . '/database.sqlite';
                if (! is_file($db)) {
                    continue;
                }
                $list[] = [
                    'folder' => basename($folder),
                    'created_at' => date('Y-m-d H:i:s', filemtime($db)),
                    'size' => filesize($db),
                    'db_sha256' => hash_file('sha256', $db),
                ];
            }
        }
        usort($list, fn ($a, $b) => strcmp($b['folder'], $a['folder']));

        return response()->json($list);
    }

    public function delete(Request $request): JsonResponse
    {
        $folder = (string) $request->input('folder', '');
        if (! preg_match('/^mahria-\d{8}-\d{6}$/', $folder)) {
            return response()->json(['message' => 'Invalid backup folder.'], 422);
        }

        $dir = storage_path('app/backups/' . $folder);
        if (! is_dir($dir)) {
            return response()->json(['message' => 'Backup not found.'], 404);
        }

        $this->rmDir($dir);

        return response()->json(['message' => 'Backup deleted.']);
    }

    public function exportExcel(Request $request)
    {
        $allowed = ['inventory', 'sales', 'purchases', 'distributors', 'customers'];
        $sections = $request->input('sections', $allowed);
        if (! is_array($sections) || ! $sections) {
            $sections = $allowed;
        }
        $sections = array_values(array_intersect($allowed, $sections));
        if (! $sections) {
            return response()->json(['message' => 'Choose at least one section.'], 422);
        }

        $sheets = [];
        if (in_array('inventory', $sections, true)) {
            $rows = [];
            foreach (Product::withSum('batches as stock', 'quantity')->with('companyModel:id,name')->orderBy('name')->get() as $p) {
                $rows[] = [
                    $p->name,
                    $p->sku,
                    $p->barcode,
                    $p->category,
                    $p->companyModel?->name ?? $p->company ?? '',
                    $p->generic_name,
                    $p->variants,
                    $p->unit,
                    (float) $p->price,
                    (float) $p->trade_price,
                    (float) $p->cost_price,
                    $p->items_per_pack ?? '',
                    (int) $p->low_stock_alert,
                    (int) $p->stock,
                ];
            }
            $sheets[] = [
                'name' => 'Inventory',
                'headers' => ['Name', 'SKU', 'Barcode', 'Category', 'Company', 'Generic Name', 'Variants', 'Unit', 'Price', 'Trade Price', 'Cost Price', 'Items Per Pack', 'Low Stock Alert', 'Stock'],
                'rows' => $rows,
            ];
        }

        if (in_array('sales', $sections, true)) {
            $saleRows = [];
            $itemRows = [];
            foreach (Sale::with(['customer:id,name', 'user:id,name', 'items.product:id,name'])->orderBy('id')->get() as $s) {
                $saleRows[] = [
                    $s->invoice_number,
                    $s->created_at,
                    $s->customer?->name ?? 'Walk-in',
                    $s->items->count(),
                    (float) $s->subtotal,
                    (float) $s->discount,
                    (float) $s->tax,
                    (float) $s->total,
                    (float) $s->paid,
                    (float) $s->due,
                    $s->payment_method,
                    $s->status,
                    $s->user?->name ?? '',
                ];
                foreach ($s->items as $it) {
                    $itemRows[] = [
                        $s->invoice_number,
                        $it->product?->name ?? '',
                        $it->sku ?? '',
                        $it->quantity,
                        (float) $it->unit_price,
                        (float) $it->discount,
                        (float) $it->total,
                    ];
                }
            }
            $sheets[] = ['name' => 'Sales', 'headers' => ['Invoice', 'Date', 'Customer', 'Items', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Due', 'Payment', 'Status', 'Cashier'], 'rows' => $saleRows];
            $sheets[] = ['name' => 'Sale Items', 'headers' => ['Invoice', 'Product', 'SKU', 'Qty', 'Unit Price', 'Discount', 'Total'], 'rows' => $itemRows];
        }

        if (in_array('purchases', $sections, true)) {
            $pRows = [];
            $piRows = [];
            foreach (Purchase::with(['supplier:id,name', 'user:id,name', 'items.product:id,name,sku', 'items.batch:id,batch_number'])->orderBy('id')->get() as $p) {
                $pRows[] = [
                    $p->invoice_number,
                    $p->purchase_date,
                    $p->supplier?->name ?? '',
                    $p->items->count(),
                    (float) $p->total_amount,
                    $p->notes,
                    $p->user?->name ?? '',
                ];
                foreach ($p->items as $it) {
                    $piRows[] = [
                        $p->invoice_number,
                        $it->product?->name ?? '',
                        $it->product?->sku ?? '',
                        $it->batch?->batch_number ?? '',
                        $it->quantity,
                        (float) $it->unit_cost,
                        (float) $it->total_cost,
                    ];
                }
            }
            $sheets[] = ['name' => 'Purchases', 'headers' => ['Invoice', 'Date', 'Supplier', 'Items', 'Total', 'Notes', 'User'], 'rows' => $pRows];
            $sheets[] = ['name' => 'Purchase Items', 'headers' => ['Invoice', 'Product', 'SKU', 'Batch', 'Qty', 'Unit Cost', 'Total'], 'rows' => $piRows];
        }

        if (in_array('distributors', $sections, true)) {
            $rows = [];
            foreach (Supplier::with('companies:id,name,supplier_id')->withCount('companies')->get() as $s) {
                $rows[] = [
                    $s->name,
                    $s->phone,
                    $s->email,
                    $s->address,
                    $s->companies_count,
                    implode(', ', $s->companies->pluck('name')->all()),
                ];
            }
            $sheets[] = ['name' => 'Distributors', 'headers' => ['Distributor', 'Phone', 'Email', 'Address', 'Companies', 'Bound Companies'], 'rows' => $rows];
        }

        if (in_array('customers', $sections, true)) {
            $rows = [];
            foreach (Customer::orderBy('name')->get() as $c) {
                $rows[] = [$c->name, $c->phone, $c->email, $c->address, (float) $c->credit, $c->created_at];
            }
            $sheets[] = ['name' => 'Customers', 'headers' => ['Name', 'Phone', 'Email', 'Address', 'Credit', 'Created'], 'rows' => $rows];
        }

        $tmp = Xlsx::exportSheets($sheets);

        return response()->download($tmp, 'mahria-export-' . date('Ymd-His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function restore(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file) {
            return response()->json(['message' => 'No file uploaded.'], 422);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'restore');
        try {
            $file->move(dirname($tmp), basename($tmp));

            $handle = fopen($tmp, 'rb');
            $magic = fread($handle, 16);
            fclose($handle);
            if ($magic !== "SQLite format 3\x00") {
                return response()->json(['message' => 'Not a valid SQLite database file.'], 422);
            }

            $pdo = new \PDO('sqlite:' . $tmp);
            $check = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            if ($check !== 'ok') {
                return response()->json(['message' => 'The uploaded database failed integrity checks.'], 422);
            }

            $required = ['users', 'products', 'suppliers', 'customers', 'purchases', 'sales', 'companies'];
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
            $missing = array_values(array_diff($required, $tables));
            if ($missing) {
                return response()->json(['message' => 'Not a Mehria backup (missing tables: ' . implode(', ', $missing) . ').'], 422);
            }
        } catch (\Throwable $e) {
            @unlink($tmp);

            return response()->json(['message' => 'Could not read the uploaded file: ' . $e->getMessage()], 422);
        }

        $dbPath = database_path('database.sqlite');
        if (! is_file($dbPath)) {
            @unlink($tmp);

            return response()->json(['message' => 'Live database not found.'], 500);
        }

        try {
            $backupDir = storage_path('app/backups');
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0777, true);
            }
            $pre = $backupDir . '/mahria-' . date('Ymd-His') . '-pre-restore';
            mkdir($pre, 0777, true);
            copy($dbPath, $pre . '/database.sqlite');
            @chmod($dbPath, 0666);
            copy($tmp, $dbPath);
            @unlink($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            Log::error('Restore failed: ' . $e->getMessage());

            return response()->json(['message' => 'Restore failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Restore complete. A safety copy of the previous data was saved as a backup.',
        ]);
    }

    public function importExcel(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file) {
            return response()->json(['message' => 'No file uploaded.'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls'], true)) {
            return response()->json(['message' => 'Please upload an Excel (.xlsx) file.'], 422);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        try {
            $file->move(dirname($tmp), basename($tmp));
            $sheets = Xlsx::parseSheets($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);

            return response()->json(['message' => 'Could not read the Excel file: ' . $e->getMessage()], 422);
        }
        @unlink($tmp);

        if (! isset($sheets['Inventory']) || ! $sheets['Inventory']) {
            return response()->json(['message' => 'No "Inventory" sheet found in the file. Only inventory data is imported from Excel.'], 422);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($sheets['Inventory'] as $row) {
            $name = trim((string) ($row['Name'] ?? ''));
            $sku = trim((string) ($row['SKU'] ?? ''));
            if ($name === '' && $sku === '') {
                $skipped++;
                continue;
            }

            $product = null;
            if ($sku !== '') {
                $product = Product::whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
            }
            if (! $product && $name !== '') {
                $product = Product::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            }

            $fields = [];
            foreach (['name', 'barcode', 'category', 'generic_name', 'variants', 'unit'] as $f) {
                $key = ucfirst(str_replace('_', ' ', $f));
                $key = $f === 'generic_name' ? 'Generic Name' : ($f === 'variants' ? 'Variants' : ucfirst($f));
                $v = trim((string) ($row[$key] ?? ''));
                if ($v !== '' && $v !== '0') {
                    $fields[$f] = $v;
                }
            }
            $companyText = trim((string) ($row['Company'] ?? ''));
            if ($companyText !== '') {
                $fields['company'] = $companyText;
            }
            foreach (['price', 'trade_price', 'cost_price', 'low_stock_alert'] as $f) {
                $key = $f === 'low_stock_alert' ? 'Low Stock Alert' : ucfirst($f);
                $raw = (string) ($row[$key] ?? '');
                if ($raw !== '' && is_numeric($raw)) {
                    $fields[$f] = max(0, (float) $raw);
                }
            }
            $ippRaw = (string) ($row['Items Per Pack'] ?? ($row['Sachets Per Pack'] ?? ''));
            if ($ippRaw !== '' && is_numeric($ippRaw)) {
                $fields['items_per_pack'] = max(1, (int) $ippRaw);
            }

            if ($product) {
                if ($fields) {
                    $product->update($fields);
                    $updated++;
                }
            } elseif ($name !== '') {
                $fields['name'] = $name;
                $fields['sku'] = $sku !== '' ? $sku : 'IMP-' . strtoupper(substr(md5($name), 0, 8));
                $fields['price'] = $fields['price'] ?? 0;
                $fields['cost_price'] = $fields['cost_price'] ?? 0;
                $fields['unit'] = $fields['unit'] ?? 'tablet';
                $fields['low_stock_alert'] = $fields['low_stock_alert'] ?? 10;
                $fields['is_active'] = true;
                Product::create($fields);
                $created++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'message' => "Excel inventory import finished: {$created} created, {$updated} updated, {$skipped} skipped. Nothing else was changed.",
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function copyDir(string $src, string $dst): void
    {
        if (! is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        foreach (scandir($src) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $entry;
            if (is_file($from)) {
                copy($from, $dst . DIRECTORY_SEPARATOR . $entry);
            }
        }
    }

    private function prune(string $backupDir): void
    {
        $folders = array_values(array_filter(glob($backupDir . '/mahria-*'), 'is_dir'));
        rsort($folders);
        foreach (array_slice($folders, 10) as $old) {
            $this->rmDir($old);
        }
    }

    private function rmDir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->rmDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}