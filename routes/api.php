<?php

use App\Http\Controllers\Api\DiscountCodeController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\OrderTypeController;
use App\Http\Controllers\Api\Admin\AdminFinancialController;
use App\Http\Controllers\Api\FinancialTransactionController;
use App\Http\Controllers\Api\ProductIngredientController;
use App\Http\Controllers\Api\ContributionController;
use App\Http\Controllers\Api\Admin\AdminPlanController;
use App\Http\Controllers\Api\Admin\AdminCafeProductController;
use App\Http\Controllers\Api\Admin\AdminCafeUserController;
use App\Http\Controllers\Api\PublicMenuController;
use App\Http\Controllers\Api\ReportController;  
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CafeAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryTransactionController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SubscriptionPaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::get('/subscription-plans', [SubscriptionPaymentController::class, 'plans']);
Route::post('/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1');
Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');


// callback زرین‌پال (بدون نیاز به توکن)
Route::get('/subscription-payments/{subscriptionTransaction}/verify', [SubscriptionPaymentController::class, 'verify']);

Route::middleware(['auth:sanctum', 'active.user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/subscription-payments/initiate', [SubscriptionPaymentController::class, 'initiate'])->middleware('throttle:10,1');
    Route::get('/subscription-status', [SubscriptionPaymentController::class, 'status']);

    Route::middleware(['cafe.subscription.active'])->group(function () {

        Route::middleware('check.permission:product.read')->group(function () {
            Route::get('/product-categories', [ProductCategoryController::class, 'index']);
            Route::get('/product-categories/{productCategory}', [ProductCategoryController::class, 'show']);
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/products/{product}', [ProductController::class, 'show']);
            Route::get('/products/{product}/ingredients', [ProductIngredientController::class, 'index']);
            Route::post('/products/{product}/ingredients', [ProductIngredientController::class, 'store']);
            Route::delete('/products/{product}/ingredients/{productIngredient}', [ProductIngredientController::class, 'destroy']);
        });

        Route::middleware('check.permission:product.write')->group(function () {
            Route::post('/product-categories', [ProductCategoryController::class, 'store']);
            Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
            Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);

            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        });

        Route::middleware('check.permission:inventory.read')->group(function () {
            Route::get('/inventory-items', [InventoryItemController::class, 'index']);
            Route::get('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'show']);
            Route::get('/inventory-transactions', [InventoryTransactionController::class, 'index']);
        });

        Route::middleware('check.permission:inventory.write')->group(function () {
            Route::post('/inventory-items', [InventoryItemController::class, 'store']);
            Route::put('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
            Route::delete('/inventory-items/{inventoryItem}', [InventoryItemController::class, 'destroy']);
            Route::post('/inventory-transactions', [InventoryTransactionController::class, 'store']);
            Route::get('/inventory-alerts', [InventoryItemController::class, 'lowStockAlerts']);
        });

        Route::middleware('check.permission:order.read')->group(function () {
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::post('/orders/{order}/invoice', [InvoiceController::class, 'store']);
            Route::put('/orders/{order}', [OrderController::class, 'update']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        });

        Route::middleware('check.permission:order.create')->group(function () {
            Route::post('/orders', [OrderController::class, 'store']);
        });

        Route::middleware('check.permission:order.cancel')->group(function () {
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        });

        Route::middleware('check.permission:payment.create')->group(function () {
            Route::post('/orders/{order}/payments', [PaymentController::class, 'store']);
            Route::post('/orders/{order}/refund', [PaymentController::class, 'refund']);
        });

        Route::middleware('check.permission:users.read')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/{user}', [UserController::class, 'show']);
        });

        Route::middleware('check.permission:users.write')->group(function () {
            Route::post('/users', [UserController::class, 'store']);
            Route::put('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
        }); 

        Route::middleware('check.permission:report.read')->group(function () {
            Route::get('/reports/sales', [ReportController::class, 'sales']);
            Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
            Route::get('/reports/payment-methods', [ReportController::class, 'paymentMethods']);
            Route::get('/reports/cancellations', [ReportController::class, 'cancellations']);
            Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);
            Route::get('/reports/orders', [ReportController::class, 'orders']);
            Route::get('/reports/payments-by-method', [ReportController::class, 'paymentsByMethod']);
        });

        Route::middleware('check.permission:finance.read')->group(function () {
            Route::get('/financial-transactions', [FinancialTransactionController::class, 'index']);
            Route::get('/financial-summary', [FinancialTransactionController::class, 'summary']);
            Route::get('/financial-transactions/{financialTransaction}/details', [FinancialTransactionController::class, 'details']);

        });
        
        Route::middleware('check.permission:finance.write')->group(function () {
            Route::post('/financial-transactions', [FinancialTransactionController::class, 'store']);
            Route::delete('/financial-transactions/{financialTransaction}', [FinancialTransactionController::class, 'destroy']);
        });

        Route::middleware('check.permission:order.read')->group(function () {
            Route::get('/order-types', [OrderTypeController::class, 'index']);
        });
        
        Route::middleware('check.permission:table.read')->group(function () {
            Route::get('/tables', [TableController::class, 'index']);
        });
        
        Route::middleware('check.permission:table.write')->group(function () {
            Route::post('/tables', [TableController::class, 'store']);
            Route::put('/tables/{table}', [TableController::class, 'update']);
        });

        Route::middleware('check.permission:order.create')->group(function () {
            Route::get('/discount-codes', [DiscountCodeController::class, 'index']);
        });
        
        Route::middleware('check.permission:order.create')->group(function () {
            // برای مدیر/مالک: ساخت و مدیریت کد
        });
        Route::middleware('check.permission:discount.write')->group(function () {
            Route::post('/discount-codes', [DiscountCodeController::class, 'store']);
            Route::put('/discount-codes/{discountCode}', [DiscountCodeController::class, 'update']);
        });

    });
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/otp/request', [AdminAuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('/otp/verify', [AdminAuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    
    Route::middleware(['auth:admin', 'active.user'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);
        
        Route::get('/dashboard', [CafeAdminController::class, 'dashboard']);
        
        Route::get('/cafes', [CafeAdminController::class, 'index']);
        Route::post('/cafes', [CafeAdminController::class, 'store']);
        Route::get('/cafes/{cafe}', [CafeAdminController::class, 'show']);
        Route::patch('/cafes/{cafe}/status', [CafeAdminController::class, 'updateStatus']);
        
        Route::get('/cafes/{cafe}/products', [AdminCafeProductController::class, 'index']);
        Route::post('/cafes/{cafe}/products', [AdminCafeProductController::class, 'store']);
        Route::put('/cafes/{cafe}/products/{product}', [AdminCafeProductController::class, 'update']);
        Route::delete('/cafes/{cafe}/products/{product}', [AdminCafeProductController::class, 'destroy']);
        
        Route::get('/cafes/{cafe}/users', [AdminCafeUserController::class, 'index']);
        Route::post('/cafes/{cafe}/users', [AdminCafeUserController::class, 'store']);
        Route::put('/cafes/{cafe}/users/{user}', [AdminCafeUserController::class, 'update']);
        Route::delete('/cafes/{cafe}/users/{user}', [AdminCafeUserController::class, 'destroy']);
        Route::delete('/cafes/{cafe}', [CafeAdminController::class, 'destroy']);
        Route::post('/cafes/{cafe}/activate-subscription', [CafeAdminController::class, 'activateSubscription']);
        
        
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy']);
        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::match(['put', 'patch'], '/plans/{plan}', [AdminPlanController::class, 'update']);
        
        
        Route::post('/cafes/{cafe}/impersonate', [CafeAdminController::class, 'impersonate']);

        
        Route::get('/financial/summary', [AdminFinancialController::class, 'summary']);
        Route::get('/financial/per-cafe', [AdminFinancialController::class, 'perCafe']);
    });
});

Route::get('/public/cafes/{cafe}/menu', [PublicMenuController::class, 'show']);

Route::post('/contributions/initiate', [ContributionController::class, 'initiate'])->middleware('throttle:10,1');
Route::get('/contributions/{contribution}/verify', [ContributionController::class, 'verify']);