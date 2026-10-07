<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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

        // کافه + تاریخ سفارش + شماره‌ی روزانه = یکتا
        $invoiceNumber = $order->cafe_id . '-'
            . $order->created_at->format('Ymd') . '-'
            . ($order->order_number ?? $order->id);

        // اگر دو درخواست هم‌زمان بیاید، unique(order_id) یکی را رد می‌کند
        $invoice = Invoice::createOrFirst(
            ['order_id' => $order->id],
            ['invoice_number' => $invoiceNumber]
        );

        if (! $invoice->wasRecentlyCreated) {
            return response()->json($invoice, 200);
        }

        $order->load('items.product', 'cafe');

        $html = view('invoices.template', [
            'invoice' => $invoice,
            'order' => $order,
        ])->render();

        // نام فایل تصادفی، روی دیسک خصوصی
        $path = 'invoices/' . Str::uuid() . '.pdf';
        Storage::disk('local')->makeDirectory('invoices');
        $fullPath = Storage::disk('local')->path($path);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($fullPath, Destination::FILE);

        $invoice->update(['pdf_path' => $path]);

        return response()->json($invoice->fresh(), 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $order = Order::withoutGlobalScopes()->find($invoice->order_id);

        if (! $order || $order->cafe_id !== $request->user()->cafe_id) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $invoice->pdf_path || ! $disk->exists($invoice->pdf_path)) {
            return response()->json(['message' => 'فایل فاکتور پیدا نشد.'], 404);
        }

        return response()->file($disk->path($invoice->pdf_path));
    }
}