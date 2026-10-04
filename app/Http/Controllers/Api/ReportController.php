<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * ۱. گزارش فروش در بازه‌ی تاریخی
     */
    public function sales(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $orders = Order::where('status', 'paid')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total_amount'),
            'average_order_value' => $orders->count() > 0
                ? round($orders->avg('total_amount'), 2)
                : 0,
        ]);
    }

    /**
     * ۲. پرفروش‌ترین محصولات
     */ 
    public function topProducts(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $limit = $request->query('limit', 10);
        $cafeId = $request->user()->cafe_id;

        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.cafe_id', $cafeId)
            ->where('orders.status', 'paid')
            ->whereDate('orders.created_at', '>=', $from)
            ->whereDate('orders.created_at', '<=', $to)
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'products' => $items,
        ]);
    }

    /**
     * ۳. فروش به تفکیک روش پرداخت
     */
    public function paymentMethods(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $cafeId = $request->user()->cafe_id;

        $result = Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.cafe_id', $cafeId)
            ->where('payments.status', 'success')
            ->whereDate('payments.created_at', '>=', $from)
            ->whereDate('payments.created_at', '<=', $to)
            ->select('payments.method', DB::raw('COUNT(*) as count'), DB::raw('SUM(payments.amount) as total'))
            ->groupBy('payments.method')
            ->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'by_method' => $result,
        ]);
    }

    /**
     * ۴. گزارش لغو سفارش‌ها
     */
    public function cancellations(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $cancelledBefore = Order::where('status', 'cancelled_before_payment')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $cancelledAfter = Order::where('status', 'cancelled_after_payment')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'cancelled_before_payment' => $cancelledBefore,
            'cancelled_after_payment' => $cancelledAfter,
            'total_cancelled' => $cancelledBefore + $cancelledAfter,
        ]);
    }

    /**
     * ۵. وضعیت انبار (کالاهای کم‌موجودی)
     */
    public function lowStock(Request $request)
    {
        $items = InventoryItem::whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->orderBy('current_stock')
            ->get();

        return response()->json([
            'items' => $items,
        ]);
    }


    /**
 * ۶. لیست سفارش‌ها با فیلتر میز/بازه‌ی تاریخی
 */
    public function orders(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $query = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with('items.product', 'orderType', 'table');

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->query('table_id'));
        }

        $orders = $query->latest()->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->where('status', 'paid')->sum('total_amount'),
            'orders' => $orders,
        ]);
    }


        /**
     * ۷. گزارش پرداخت‌ها به تفکیک روش
     */
    public function paymentsByMethod(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $cafeId = $request->user()->cafe_id;

        $payments = Payment::where('status', 'success')
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereHas('order', fn ($q) => $q->where('cafe_id', $cafeId))
            ->get();

        $methodLabels = [
            'cash' => 'نقدی',
            'manual_card_reader' => 'کارت‌خوان',
            'online_gateway' => 'درگاه آنلاین',
        ];

        $byMethod = $payments->groupBy('method')->map(function ($group, $method) use ($methodLabels) {
            return [
                'method' => $method,
                'label' => $methodLabels[$method] ?? $method,
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ];
        })->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'total' => $payments->sum('amount'),
            'by_method' => $byMethod,
        ]);
    }
}