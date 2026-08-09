# POS System — Integration Report (vs. Reference CodeIgniter 3 App)

Project: **Mehria Medicine Company** — Laravel 13 (API) + Vue 3 (SPA) POS
Reference: CodeIgniter 3 POS with mike42/escpos-php thermal printing.

---

## 1. Page-by-Page Summary of Changes

| Page (my system) | What was added / changed to match the reference |
|---|---|
| **POS** (`/pos`) | Product search by name/SKU/barcode + Enter-to-add; live cart with qty +/-; % discount. **NEW:** Amount Paid field (auto-fills with total, editable), live **Change** / **Balance Due** computation, Payment Method (Cash / Card / Mobile Wallet / **Credit**), partial payments allowed — credit balance is posted to the selected customer. Full-payment required for walk-ins (backend enforced). F2 = Charge, F3 = Charge & Print. |
| **Receipt print** | Popup print window now matches the reference template: shop name, Receipt #, Date+Time, Cashier, Customer, Method, item table (Item / QTY / Price / Amount), Subtotal, Discount, TAX (only if > 0), TOTAL, Paid (Cash), **Change**, **Balance Due** (credit sales), "Thank you! Come again" footer. Fallback alert if popups blocked. Shared renderer in `frontend/src/utils.js` (`receiptHtml` / `printSaleReceipt`). |
| **Sales** (`/sales`, NEW) | Searchable sale history: invoice-# search, from/to date filters, status filter, pagination. Columns: Receipt #, Date, Cashier, Customer, Method, Items, Total, Paid, Due, Status. Actions: **Reprint** (same receipt popup), **View** (invoice modal), **Refund** (restores batch stock and reverses customer credit). |
| **Dashboard** | Unchanged (today revenue/profit, low stock, expiring batches, recent sales). |
| **Inventory / Purchases / Customers / Users** | Unchanged. Customers page will show the new `credit` balance column data where applicable. |
| **Reports** (`/reports`) | **NEW:** "Sales by Cashier" panel (sales, revenue, collected, credit-due) for the selected 7/30/90-day range — matches reference "daily sales by cashier". **NEW:** Excel exports (Products & Stock, Batches & Expiry, Suppliers, Customers, Sales) as real `.xlsx` downloads. |
| **Refund flow** | Reference "purchase_return / refund_reciept": refunding a sale restores stock to the original FEFO batches, marks sale `refunded`, and **deducts the unpaid due from the customer's credit balance**. |

## 2. DB Schema Diffs (my system vs reference)

### Tables added for parity
| Table | Purpose | Reference equivalent |
|---|---|---|
| `users` (role: `admin`/`cashier`) | Login + cashier attribution | `users` |
| `products` | Item master (name, generic_name, sku, barcode, price, cost_price, unit, low_stock_alert, is_active) | `products` |
| `batches` | Stock per expiry batch (FEFO deduction) | stock per product + `purchase` lines |
| `suppliers` | Company/company master | `supplier` |
| `customers` | Customer master + **`credit` balance** (NEW) | `customers` (+ credit ledger) |
| `purchases` / `purchase_items` | Purchase + stock-in (creates batches) | `purchase` / `purchase_items` |
| `sales` / `sale_items` | Invoices + lines | `sales` / `sales_items` |

### Column diffs
| My system | Reference | Notes |
|---|---|---|
| `sales.invoice_number` (`INV-YYYYMMDD-XXXX`) | `SA-00123` style | Format differs; kept ours, sequential numbering can be swapped in `SaleController::store` |
| `sales.paid`, **`sales.due`** (NEW) | `paid` + credit due | `due = max(0, total − paid)`; posted to `customers.credit` |
| `sales.payment_method` enum: `cash,card,mobile,credit` (NEW `credit`) | `Cash / Card / Credit / Partial` | `credit` auto-set when `due > 0` |
| `sales.tax` | `tax` (%) | Stored as computed amount, printed only when > 0 |
| `customers.credit` (NEW) | customer credit balance | `increment` on credit sale, `decrement` on refund |
| `sale_items.batch_id` | — | FEFO batch tracking for exact stock restore |

## 3. Receipt Print Code + Settings Example

### 3a. Browser-print fallback (current, works everywhere)
`frontend/src/utils.js` → `receiptHtml(sale)` builds the receipt DOM; `printSaleReceipt(sale)` opens a popup and calls `window.print()`. Use the popup if a thermal printer is not configured.

### 3b. ESC/POS driver settings (on-premise printer, reference parity)
Requires `composer require mike42/escpos-php` in `backend/`, then in the sale controller (or a queued job after `Sale::create`):

```php
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

// Windows share name configured per machine, e.g. "POS-58"
$connector = new WindowsPrintConnector('POS-58');           // Windows shared printer
// $connector = new FilePrintConnector('PHP_POS');          // USB/Linux fallback
// $connector = new NetworkPrintConnector('192.168.1.50', 9100); // Network printer

try {
    $printer = new Printer($connector);
    $printer->initialize();
    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
    $printer->text("Mehria Medicine Company\n");
    $printer->selectPrintMode();
    $printer->text("Receipt #: {$sale->invoice_number}\n");
    $printer->text('Date: ' . now()->format('d-m-Y H:i') . "\n");
    $printer->text("Cashier: {$request->user()->name}\n");
    $printer->text('Customer: ' . ($customer?->name ?? 'Walk-in') . "\n");
    $printer->text(str_repeat('-', 32) . "\n");
    $printer->text("Item              QTY  Price  Subtotal\n");
    foreach ($prepared as $p) {
        $printer->text(sprintf("%-16s %-4d %-6.2f %-8.2f\n",
            substr($p['product']->name, 0, 16), $p['quantity'], $p['unit_price'],
            $p['quantity'] * $p['unit_price']));
    }
    $printer->text(str_repeat('-', 32) . "\n");
    $printer->text(sprintf("Subtotal: %.2f\n", $subtotal));
    if ($discount > 0) $printer->text(sprintf("Discount: %.2f\n", $discount));
    if ($tax > 0)      $printer->text(sprintf("TAX: %.2f\n", $tax));
    $printer->text(sprintf("TOTAL: %.2f\n", $total));
    $printer->text(sprintf("Cash: %.2f\n", $paid));
    $printer->text(sprintf("Change: %.2f\n", max(0, $paid - $total)));
    if ($due > 0) $printer->text(sprintf("Balance Due: %.2f\n", $due));
    $printer->feed(1);
    $printer->text("Thank you! Come again\n");
    $printer->cut();
    $printer->close();
} catch (\Throwable $e) {
    // Printer unavailable → frontend already falls back to the browser popup (printSaleReceipt)
    \Illuminate\Support\Facades\Log::warning('Receipt print failed: ' . $e->getMessage());
}
```

Error handling contract: any printer failure is caught, logged, and the Vue SPA still opens the browser print popup (the `Print / Save PDF` button) so the sale is never blocked.
