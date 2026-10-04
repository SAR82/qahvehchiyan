<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function store(Request $request, Order $order)
    {
        if ($order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        if ($order->status !== 'paid') {
            return response()->json([
                'message' => 'فقط سفارش پرداخت‌شده فاکتور می‌گیرد.',
            ], 422);
        }

        if ($order->invoice) {
            return response()->json($order->invoice, 200);
        }

        $invoiceNumber = $order->order_number ?? $order->id;

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
        ]);

        $order->load('items.product', 'cafe');

        $html = view('invoices.template', [
            'invoice' => $invoice,
            'order' => $order,
        ])->render();

        $directory = storage_path('app/public/invoices');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = "invoices/{$invoice->invoice_number}.pdf";
        $fullPath = storage_path("app/public/{$path}");

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($fullPath, Destination::FILE);

        $invoice->update([
            'pdf_path' => $path,
        ]);

        return response()->json($invoice->fresh(), 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $order = \App\Models\Order::withoutGlobalScopes()->find($invoice->order_id);

        if (! $order || $order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        $fullPath = storage_path("app/public/{$invoice->pdf_path}");

        if (! file_exists($fullPath)) {
            return response()->json(['message' => 'فایل فاکتور پیدا نشد.'], 404);
        }

        return response()->file($fullPath);
    }
}