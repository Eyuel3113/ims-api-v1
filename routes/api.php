<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ExpenseController;




Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Protected routes — authenticated
    Route::middleware('auth:sanctum')->group(function () {

      Route::prefix('auth')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-email', [AuthController::class, 'changeEmail']);
     
      });


      Route::prefix('categories')->group(function () {

        Route::get('/active', [CategoryController::class, 'activeCategories']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::patch('/{id}', [CategoryController::class, 'update']);
        Route::patch('/{id}/status', [CategoryController::class, 'toggleStatus']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);

      });

      Route::prefix('suppliers')->group(function () {
        Route::get('/active', [SupplierController::class, 'activeSuppliers']);
        Route::get('/', [SupplierController::class, 'index']);
        Route::post('/', [SupplierController::class, 'store']);
        Route::get('/{id}', [SupplierController::class, 'show']);
        Route::patch('/{id}', [SupplierController::class, 'update']);
        Route::patch('/{id}/status', [SupplierController::class, 'toggleStatus']);
        Route::delete('/{id}', [SupplierController::class, 'destroy']);
      });

      // Warehouses
      Route::prefix('warehouses')->group(function () {
        Route::get('/active', [WarehouseController::class, 'activeWarehouses']);
        Route::get('/', [WarehouseController::class, 'index']);
        Route::post('/', [WarehouseController::class, 'store']);
        Route::get('/{id}', [WarehouseController::class, 'show']);
        Route::patch('/{id}', [WarehouseController::class, 'update']);
        Route::patch('/{id}/status', [WarehouseController::class, 'toggleStatus']);
        Route::delete('/{id}', [WarehouseController::class, 'destroy']);
      });

      // Products
      Route::prefix('products')->group(function () {
        Route::get('/active-list', [ProductController::class, 'activeProductsList']);
        Route::get('/active', [ProductController::class, 'activeProducts']);
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::post('/import', [ProductController::class, 'import']);
        Route::get('/barcode-search', [ProductController::class, 'searchByBarcode']);
        Route::get('/{id}', [ProductController::class, 'show']);
        Route::patch('/{id}', [ProductController::class, 'update']);
        Route::patch('/{id}/status', [ProductController::class, 'toggleStatus']);
        Route::post('/{id}/photo', [ProductController::class, 'uploadPhoto']);
        Route::delete('/{id}/photo', [ProductController::class, 'deletePhoto']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::get('/{id}/barcode', [ProductController::class, 'barcodeImage']);
        Route::get('/{id}/stock-movements', [StockMovementController::class, 'productMovementHistory']);

      });

      Route::prefix('purchases')->group(function () {
        Route::get('/active', [PurchaseController::class, 'activePurchases']);
        Route::get('/', [PurchaseController::class, 'index']);
        Route::post('/', [PurchaseController::class, 'store']);
        Route::get('/{id}', [PurchaseController::class, 'show']);
        Route::patch('/{id}', [PurchaseController::class, 'update']);
        Route::patch('/{id}/status', [PurchaseController::class, 'toggleStatus']);
        Route::delete('/{id}', [PurchaseController::class, 'destroy']);
        Route::get('/{id}/invoice', [PurchaseController::class, 'invoice']);
        Route::post('/{id}/receive', [PurchaseController::class, 'receiveStatus']);
        Route::post('/{id}/cancel', [PurchaseController::class, 'cancelStatus']);
    });

      // Sales
      Route::prefix('sales')->group(function () {
        Route::get('/', [SaleController::class, 'index']);
        Route::post('/', [SaleController::class, 'store']);
        Route::get('/{id}', [SaleController::class, 'show']);
        Route::patch('/{id}', [SaleController::class, 'update']);
        Route::delete('/{id}', [SaleController::class, 'destroy']);
        Route::get('/{id}/invoice', [SaleController::class, 'invoice']);
      });

    // Stock History
    Route::get('/stock-movements', [StockMovementController::class, 'index']);
    Route::post('/stock-movements', [StockMovementController::class, 'store']);

    // Activity Logs
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
    });

    // Analytics Dashboard
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/stock-by-warehouse', [AnalyticsController::class, 'stockByWarehouse']);
        Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
        Route::get('/inventory-by-category', [AnalyticsController::class, 'inventoryByCategory']);
        Route::get('/purchase-kpis', [AnalyticsController::class, 'purchaseKpis']);
        Route::get('/expiring-stock', [AnalyticsController::class, 'expiringStock']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/overview', [ReportsController::class, 'overview']);
        Route::get('/sales', [ReportsController::class, 'sales']);
        Route::get('/inventory', [ReportsController::class, 'inventory']);
        Route::get('/profit-loss', [ReportsController::class, 'profitLoss']);
        Route::get('/tax', [ReportsController::class, 'taxReport']);
    });

    // Expenses
    Route::get('expenses/all', [ExpenseController::class, 'listByCategory']);
    Route::apiResource('expenses', ExpenseController::class);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });
});

});