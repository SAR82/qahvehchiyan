<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 14px; direction: rtl; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: right; }
        h2 { margin-bottom: 0; }
        .total { font-weight: bold; font-size: 16px; margin-top: 20px; }
    </style>
</head>
<body>
    <h2>فاکتور فروش</h2>
    <p>شماره فاکتور: <span dir="ltr" style="unicode-bidi: embed;">{{ $invoice->invoice_number }}</span></p>
    <p>تاریخ: <span dir="ltr" style="unicode-bidi: embed;">{{ \App\Support\Jalali::fromCarbon($invoice->created_at, true) }}</span></p>
    <p>کافه: {{ $order->cafe->name }}</p>

    <table>
        <thead>
            <tr>
                <th>محصول</th>
                <th>تعداد</th>
                <th>قیمت واحد</th>
                <th>جمع</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->unit_price) }}</td>
                    <td>{{ number_format($item->unit_price * $item->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        // تفکیک تخفیف و مالیات فقط برای نمایش؛ از روی اقلام سفارش محاسبه می‌شود و چیزی در دیتابیس ذخیره نمی‌شود
        $ratio = (float) $order->discount_percentage / 100;
        $subtotal = 0;
        $discount = 0;
        foreach ($order->items as $item) {
            $line = $item->unit_price * $item->quantity;
            $subtotal += $line;
            if ($item->product && $item->product->is_discountable) {
                $discount += $line * $ratio;
            }
        }
        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);
        $afterDiscount = $subtotal - $discount;
        // مالیات = مبلغ نهایی ثبت‌شده منهای مبلغ بعد از تخفیف، تا فاکتور همیشه دقیقاً جمع بزند
        $tax = round((float) $order->total_amount - $afterDiscount, 2);
    @endphp

    <table style="width: 60%; margin-right: 0; margin-left: auto;">
        <tr>
            <td>جمع اقلام</td>
            <td>{{ number_format($subtotal) }} تومان</td>
        </tr>
        @if ($discount > 0)
            <tr>
                <td>تخفیف ({{ (float) $order->discount_percentage }}٪@if ($order->discountCode) - {{ $order->discountCode->code }}@endif)</td>
                <td>- {{ number_format($discount) }} تومان</td>
            </tr>
            <tr>
                <td>مبلغ پس از تخفیف</td>
                <td>{{ number_format($afterDiscount) }} تومان</td>
            </tr>
        @endif
        @if ($tax > 0)
            <tr>
                <td>مالیات ({{ (float) $order->tax_percentage }}٪)</td>
                <td>+ {{ number_format($tax) }} تومان</td>
            </tr>
        @endif
        <tr>
            <td style="font-weight: bold; font-size: 16px;">مبلغ نهایی</td>
            <td style="font-weight: bold; font-size: 16px;">{{ number_format($order->total_amount) }} تومان</td>
        </tr>
    </table>
</body>
</html>