<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

class PrintController extends Controller
{
    private const RAAST_ID = 'PK52MSHQ0000089200047319';

    public function receipt(Request $request)
    {
        $request->validate(['sale_id' => 'required|integer']);

        $sale = Sale::with(['customer:id,name,phone', 'user:id,name', 'items.product:id,name', 'items.batch:id,batch_number'])
            ->findOrFail($request->integer('sale_id'));

        $printerName = env('THERMAL_PRINTER', 'BC-80POS');

        try {
            $connector = new FilePrintConnector('\\\\localhost\\' . $printerName);
            $printer = new Printer($connector);
            $printer->initialize();
            $printer->setEmphasis(true);

            $this->printRaster($printer, storage_path('app/thermal-logo.png'), 320);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("MAHRIA MEDICINE COMPANY\n");
            $printer->setEmphasis(false);
            $printer->text("BANGLA ROAD NEAR AGRICULTURE OFFICE,\n");
            $printer->text("HAROONABAD\n");
            $printer->text("CONTACT # 0345-2863883\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $this->line($printer);

            $printer->text(sprintf("%-8s%s\n", 'Receipt#', $sale->invoice_number));
            $printer->text(sprintf("%-8s%s\n", 'Date', $sale->created_at->format('d-m-Y H:i')));
            $printer->text(sprintf("%-8s%s\n", 'Cashier', $this->col($sale->user?->name, 30)));
            $printer->text(sprintf("%-8s%s\n", 'Customer', $this->col($sale->customer?->name ?? 'Walk-in', 30)));
            $printer->text(sprintf("%-8s%s\n", 'Method', strtoupper($sale->payment_method ?? 'CASH')));

            $this->line($printer);

            $printer->text($this->stretch(sprintf("%-15s%3s%7s%7s\n", 'Item', 'Qty', 'Price', 'Amt')));
            $printer->text(str_repeat('-', 32) . "\n");
            foreach ($sale->items as $it) {
                $printer->text(sprintf("%-15.15s%3d%7.2f%7.2f\n",
                    $it->product?->name ?? '?', $it->quantity, $it->unit_price, $it->quantity * $it->unit_price));
            }

            $this->line($printer);

            $printer->text(sprintf("%-25s%7.2f\n", 'SUB TOTAL', $sale->subtotal));
            if ((float) $sale->discount > 0) {
                $pct = $sale->subtotal > 0 ? round($sale->discount / $sale->subtotal * 100) : 0;
                $printer->text(sprintf("%-25s%7.2f\n", "DISCOUNT ($pct%)", -$sale->discount));
            }
            if ((float) $sale->tax > 0) {
                $printer->text(sprintf("%-25s%7.2f\n", 'TAX', $sale->tax));
            }
            $printer->setEmphasis(true);
            $printer->text(sprintf("%-25s%7.2f\n", 'TOTAL', $sale->total));
            $printer->setEmphasis(false);
            $printer->text(sprintf("%-25s%7.2f\n", 'PAID', $sale->paid));
            if ($sale->total > $sale->paid) {
                $printer->text(sprintf("%-25s%7.2f\n", 'BALANCE DUE', max(0, $sale->total - $sale->paid)));
            } else {
                $printer->text(sprintf("%-25s%7.2f\n", 'CHANGE', max(0, $sale->paid - $sale->total)));
            }

            $this->line($printer);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Thank you! Come again\n");

            $printer->setEmphasis(true);
            $printer->text("PAY VIA RAAST ONLINE PAYMENT\n");
            $printer->setEmphasis(false);
            $this->printRaster($printer, storage_path('app/QR.png'), 200);
            $printer->text('Raast ID: ' . self::RAAST_ID . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->feed(1);

            $printer->cut();
            $printer->close();

            return response()->json(['message' => 'Receipt printed.', 'printed' => true]);
        } catch (\Throwable $e) {
            Log::warning('Receipt print failed: ' . $e->getMessage());
            return response()->json(['message' => 'Printer unavailable. Use the on-screen print dialog.', 'printed' => false], 422);
        }
    }

    private function line(Printer $printer): void
    {
        $printer->text(str_repeat('-', 32) . "\n");
    }

    private function col(string $s, int $len): string
    {
        return mb_str_pad($s ?? '', $len, ' ', STR_PAD_RIGHT);
    }

    private function stretch(string $s): string
    {
        return str_replace(' ', '  ', rtrim($s));
    }

    private function printRaster(Printer $printer, string $path, int $maxWidth): void
    {
        if (! is_file($path)) {
            return;
        }
        try {
            $info = getimagesize($path);
            $src = match ($info[2] ?? 0) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($path),
                IMAGETYPE_PNG => imagecreatefrompng($path),
                default => null,
            };
            if (! $src) {
                return;
            }
            $w = imagesx($src);
            $h = imagesy($src);
            if ($w > $maxWidth) {
                $tw = $maxWidth;
                $th = (int) round($h * $maxWidth / $w);
            } else {
                $tw = $w;
                $th = $h;
            }
            $dst = imagecreatetruecolor($tw, $th);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
            imagedestroy($src);
            $img = new GdEscposImage();
            $img->readImageFromGdResource($dst);
            imagedestroy($dst);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->graphics($img);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        } catch (\Throwable $e) {
            Log::info('Raster print skipped: ' . $e->getMessage());
        }
    }
}