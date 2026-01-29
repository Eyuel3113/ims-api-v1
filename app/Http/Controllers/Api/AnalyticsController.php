<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Dashboard Analytics
     * 
     * Key metrics for inventory dashboard.
     * 
     * @group Analytics
     */
    public function dashboard()
    {
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockProducts=Product::where('is_active', true)
            ->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id)')
            ->count();
        
        $oneMonthFromNow = now()->addMonth();
        $expiringStockCount = Stock::where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', $oneMonthFromNow)
            ->where('quantity', '>', 0)
            ->count();
        
        // Low Stock Products List
        $lowStockProductsList = Product::where('is_active', true)
            ->whereRaw('min_stock > (SELECT COALESCE(SUM(quantity), 0) FROM stocks WHERE stocks.product_id = products.id)')
            ->limit(5)
            ->get(['id', 'name', 'code', 'min_stock'])
            ->map(function ($product) {
                $product->current_stock = $product->stocks()->sum('quantity');
                return $product;
            });

        $totalWarehouses = Warehouse::where('is_active', true)->count();
        $totalSuppliers = Supplier::where('is_active', true)->count();

        $totalStockValue = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->selectRaw('SUM(stocks.quantity * products.purchase_price) as value')
            ->value('value') ?? 0;

        // Sales Metrics
        $currentMonthSales = Sale::whereMonth('sale_date', now()->month)
            ->whereYear('sale_date', now()->year)
            ->sum('total_amount');

        $lastMonthSales = Sale::whereMonth('sale_date', now()->subMonth()->month)
            ->whereYear('sale_date', now()->subMonth()->year)
            ->sum('total_amount');

        $salesChangePercent = $lastMonthSales > 0 
            ? (($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100 
            : ($currentMonthSales > 0 ? 100 : 0);

        // Profit Metrics
        $currentMonthProfit = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereMonth('sales.sale_date', now()->month)
            ->whereYear('sales.sale_date', now()->year)
            ->selectRaw('SUM(sale_items.total_price - (sale_items.quantity * products.purchase_price)) as profit')
            ->value('profit') ?? 0;

        $lastMonthProfit = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereMonth('sales.sale_date', now()->subMonth()->month)
            ->whereYear('sales.sale_date', now()->subMonth()->year)
            ->selectRaw('SUM(sale_items.total_price - (sale_items.quantity * products.purchase_price)) as profit')
            ->value('profit') ?? 0;

        $currentMonthMargin = $currentMonthSales > 0 ? ($currentMonthProfit / $currentMonthSales) * 100 : 0;
        $lastMonthMargin = $lastMonthSales > 0 ? ($lastMonthProfit / $lastMonthSales) * 100 : 0;
        
        $marginChangePercent = $lastMonthMargin > 0 
            ? $currentMonthMargin - $lastMonthMargin 
            : ($currentMonthMargin > 0 ? $currentMonthMargin : 0);

        $todaySales = Sale::whereDate('sale_date', today())->sum('total_amount');
        $todayPurchases = Purchase::whereDate('purchase_date', today())->sum('total_amount');

        $topProducts = Stock::with('product')
            ->selectRaw('product_id, SUM(quantity) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->pluck('product.name', 'total');

        return response()->json([
            'message' => 'Dashboard analytics',
            'data' => [
                'total_products' => $totalProducts,
                'low_stock_products_count' => $lowStockProducts,
                'total_warehouses' => $totalWarehouses,
                'total_suppliers' => $totalSuppliers,
                'total_stock_value' => number_format($totalStockValue, 2),
                'today_sales' => number_format($todaySales, 2),
                'today_purchases' => number_format($todayPurchases, 2),
                'monthly_sales' => [
                    'amount' => number_format($currentMonthSales, 2),
                    'change_percent' => round($salesChangePercent, 2),
                    'last_month_amount' => number_format($lastMonthSales, 2),
                ],
                'profit_margin' => [
                    'percent' => round($currentMonthMargin, 2),
                    'change_points' => round($marginChangePercent, 2),
                    'last_month_percent' => round($lastMonthMargin, 2),
                ],
                'low_stock_products' => $lowStockProductsList,
                'top_products' => $topProducts,
                'expiring_stock_count' => $expiringStockCount,
            ]
        ]);
    }

    /**
     * Inventory by Category
     * 
     * Get product distribution across categories.
     * 
     * @group Analytics
     */
    public function inventoryByCategory()
    {
        $categories = DB::table('categories')
            ->leftJoin('products', function($join) {
                $join->on('categories.id', '=', 'products.category_id')
                     ->whereNull('products.deleted_at');
            })
            ->select(
                'categories.name',
                DB::raw('COUNT(products.id) as product_count')
            )
            ->where('categories.is_active', true)
            ->whereNull('categories.deleted_at')
            ->groupBy('categories.id', 'categories.name')
            ->get();

        $totalProducts = $categories->sum('product_count');

        $data = $categories->map(function ($category) use ($totalProducts) {
            $percentage = $totalProducts > 0 ? ($category->product_count / $totalProducts) * 100 : 0;
            return [
                'category' => $category->name,
                'value' => (int) $category->product_count,
                'percentage' => round($percentage, 2)
            ];
        });

        // Ensure sum is exactly 100 if there's data
        if ($data->isNotEmpty() && $totalProducts > 0) {
            $sum = $data->sum('percentage');
            if (abs(100 - $sum) > 0.0001 && $sum > 0) {
                $diff = 100 - $sum;
                // Add difference to the largest slice to avoid noticeable distortion
                $maxKey = $data->sortByDesc('percentage')->keys()->first();
                $item = $data->get($maxKey);
                $item['percentage'] = round($item['percentage'] + $diff, 2);
                $data->put($maxKey, $item);
            }
        }

        return response()->json([
            'message' => 'Product distribution by category',
            'data' => $data->values()
        ]);
    }

    /**
     * Stock Value by Warehouse
     * 
     * Total monetary value of stock in each warehouse.
     * 
     * @group Analytics
     */
    public function stockByWarehouse()
    {
        $data = Warehouse::with(['stocks.product'])
            ->get()
            ->map(function ($warehouse) {
                $value = $warehouse->stocks->sum(function ($stock) {
                    return $stock->quantity * ($stock->product->purchase_price ?? 0);
                });

                return [
                    'name' => $warehouse->name,
                    'value' => round($value, 2)
                ];
            });

        return response()->json([
            'message' => 'Stock value by warehouse',
            'data' => $data
        ]);
    }

    /**
     * Monthly Sales vs Purchase
     * 
     * Monthly trend of sales vs purchases over the last 8 months.
     * 
     * @group Analytics
     */
    public function monthlyTrend()
    {
        $months = [];
        for ($i = 7; $i >= 0; $i--) {
            $months[] = now()->subMonthsNoOverflow($i)->format('Y-m');
        }

        $data = collect($months)->map(function ($month) {
            $date = Carbon::parse($month . '-01');
            
            $salesTotal = Sale::whereMonth('sale_date', $date->month)
                ->whereYear('sale_date', $date->year)
                ->sum('total_amount');

            $purchasesTotal = Purchase::whereMonth('purchase_date', $date->month)
                ->whereYear('purchase_date', $date->year)
                ->sum('total_amount');

            return [
                'month' => $month,
                'sales' => round((float) $salesTotal, 2),
                'purchases' => round((float) $purchasesTotal, 2),
            ];
        });

        return response()->json([
            'message' => 'Monthly sales vs purchase trend',
            'data' => $data
        ]);
    }
    /**
     * Purchase KPIs
     * 
     * Key metrics for purchase status and total value.
     * 
     * @group Analytics
     */
    public function purchaseKpis()
    {   $totalPurchases = Purchase::count();
        $pendingCount = \App\Models\Purchase::where("status", "pending")->count();
        $receivedCount = \App\Models\Purchase::where("status", "received")->count();
        $totalValue = \App\Models\Purchase::sum("total_amount");

        return response()->json([
            "message" => "Purchase KPIs fetched successfully",
            "data" => [
                "total_purchases" => $totalPurchases,
                "pending_count" => $pendingCount,
                "received_count" => $receivedCount,
                "total_purchase_value" => round((float) $totalValue, 2)
            ]
        ]);
    }

    /**
     * Expiring Stock Alert
     * 
     * Get list of stocks expiring within one month.
     * 
     * @group Analytics
     */
    public function expiringStock()
    {
        $oneMonthFromNow = now()->addMonth();
        
        $expiringStocks = Stock::with(['product', 'warehouse'])
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', $oneMonthFromNow)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        return response()->json([
            'message' => 'Expiring stock fetched successfully',
            'data' => $expiringStocks
        ]);
    }
}
