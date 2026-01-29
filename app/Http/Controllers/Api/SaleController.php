<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Notification;


/**
 * @group Sales
 * Simple POS style sales where stock is reduced upon transaction.
 */
class SaleController extends Controller
{
    /**
     * List Sales
     * 
     * Get paginated list of sales with filters.
     * 
     * @queryParam limit integer optional Items per page. Default 10.
     * @queryParam status string optional active / inactive.
     * @queryParam from_date date optional Filter by sale date (from).
     * @queryParam to_date date optional Filter by sale date (to).
     * @queryParam invoice_number string optional Search by invoice number.
     * @queryParam payment_method string optional cash / card / mobile.
     * @queryParam search string optional Search by invoice or product name.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $paymentMethod = $request->query('payment_method');
        $limit = $request->query('limit', 10);

        $query = Sale::with(['items.product']);

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('sale_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('sale_date', '<=', $request->to_date);
        }

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Sales fetched successfully',
            'data' => $sales->items(),
            'pagination' => [
                'total' => $sales->total(),
                'per_page' => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
            ]
        ]);
    }


    /**
     * Create Sale
     * 
     * Record a new sale and reduce stock.
     * 
     * @bodyParam invoice_number string required
     * @bodyParam sale_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam notes string optional
     * @bodyParam payment_method string required cash / card / mobile
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:sales,invoice_number',
            'sale_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'required|string|in:cash,card,mobile',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $itemsWithPrices = [];
                $total = 0;
                $taxTotal = 0;

                foreach ($request->items as $itemData) {
                    $product = \App\Models\Product::findOrFail($itemData['product_id']);
                    $unitPrice = $itemData['unit_price'] ?? $product->selling_price;
                    $itemTotal = $itemData['quantity'] * $unitPrice;

                    $tax = 0;
                    if ($product->is_vatable) {
                        $tax = $itemTotal * 0.15;
                    }

                    $itemsWithPrices[] = array_merge($itemData, [
                        'unit_price' => $unitPrice,
                        'total_price' => $itemTotal,
                        'tax_amount' => $tax
                    ]);

                    $total += $itemTotal;
                    $taxTotal += $tax;
                }

                $sale = Sale::create([
                    'invoice_number' => $request->invoice_number,
                    'sale_date' => $request->sale_date,
                    'total_amount' => $total,
                    'tax_amount' => $taxTotal,
                    'grand_total' => $total + $taxTotal,
                    'notes' => $request->notes,
                    'payment_method' => $request->payment_method,
                ]);

                foreach ($itemsWithPrices as $item) {
                    // Reduce stock
                    $stock = Stock::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $item['warehouse_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$stock || $stock->quantity < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: " . ($stock->product->name ?? $item['product_id']));
                    }

                    $stock->quantity -= $item['quantity'];
                    $stock->save();

                    // Record Movement
                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => -$item['quantity'],
                        'type' => 'sale',
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'notes' => 'Stock sold via invoice: ' . $sale->invoice_number,
                    ]);

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                    ]);

                    // Check for Low Stock after reduction
                    $totalStock = Stock::where('product_id', $item['product_id'])->sum('quantity');
                    $product = \App\Models\Product::find($item['product_id']);
                    
                    if ($product && $product->min_stock > 0 && $totalStock <= $product->min_stock) {
                        $users = User::all();
                        Notification::send($users, new LowStockNotification($product, $totalStock));
                    }
                }

                return response()->json([
                    'message' => 'Sale recorded successfully',
                    'data' => $sale->load('items.product')
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get Sale
     * 
     * Show single sale details.
     * 
     * @urlParam id string required Sale UUID
     */
    public function show($id)
    {
        $sale = Sale::with(['items.product', 'items.warehouse'])->findOrFail($id);

        return response()->json([
            'message' => 'Sale retrieved successfully',
            'data' => $sale
        ]);
    }

    /**
     * Update Sale
     * 
     * @urlParam id string required Sale UUID
     * @bodyParam invoice_number string optional
     * @bodyParam sale_date date optional
     * @bodyParam items array optional
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam notes string optional
     * @bodyParam payment_method string optional cash / card / mobile
     */
    public function update(Request $request, $id)
    {
        $sale = Sale::with('items')->findOrFail($id);

        $request->validate([
            'invoice_number' => 'sometimes|required|string|unique:sales,invoice_number,' . $id,
            'sale_date' => 'sometimes|required|date',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'sometimes|required|string|in:cash,card,mobile',
        ]);

        try {
            return DB::transaction(function () use ($request, $sale) {
                $updateData = $request->only(['invoice_number', 'sale_date', 'notes', 'payment_method']);

                if ($request->has('items')) {
                    // 1. Revert old stock changes
                    foreach ($sale->items as $oldItem) {
                        $stock = Stock::where('product_id', $oldItem->product_id)
                            ->where('warehouse_id', $oldItem->warehouse_id)
                            ->first();

                        if ($stock) {
                            $stock->quantity += $oldItem->quantity;
                            $stock->save();

                            // Record Reversion Movement
                            StockMovement::create([
                                'product_id' => $oldItem->product_id,
                                'warehouse_id' => $oldItem->warehouse_id,
                                'quantity' => $oldItem->quantity,
                                'type' => 'adjustment',
                                'reference_type' => 'Sale',
                                'reference_id' => $sale->id,
                                'notes' => 'Stock reverted due to sale update: ' . $sale->invoice_number,
                            ]);
                        }
                    }

                    // 2. Delete existing items
                    $sale->items()->delete();

                    // 3. Fetch prices and calculate new total
                    $itemsWithPrices = [];
                    $total = 0;
                    $taxTotal = 0;
                    foreach ($request->items as $itemData) {
                        $product = \App\Models\Product::findOrFail($itemData['product_id']);
                        $unitPrice = $itemData['unit_price'] ?? $product->selling_price;
                        $itemTotal = $itemData['quantity'] * $unitPrice;

                        $tax = 0;
                        if ($product->is_vatable) {
                            $tax = $itemTotal * 0.15;
                        }

                        $itemsWithPrices[] = array_merge($itemData, [
                            'unit_price' => $unitPrice,
                            'total_price' => $itemTotal,
                            'tax_amount' => $tax
                        ]);

                        $total += $itemTotal;
                        $taxTotal += $tax;
                    }
                    $updateData['total_amount'] = $total;
                    $updateData['tax_amount'] = $taxTotal;
                    $updateData['grand_total'] = $total + $taxTotal;
                    $sale->update($updateData);

                    // 4. Create new items and reduce stock
                    foreach ($itemsWithPrices as $item) {
                        $stock = Stock::where('product_id', $item['product_id'])
                            ->where('warehouse_id', $item['warehouse_id'])
                            ->lockForUpdate()
                            ->first();

                        if (!$stock || $stock->quantity < $item['quantity']) {
                            throw new \Exception("Insufficient stock for product in update: " . ($stock->product->name ?? $item['product_id']));
                        }

                        $stock->quantity -= $item['quantity'];
                        $stock->save();

                        // Record New Movement
                        StockMovement::create([
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $item['warehouse_id'],
                            'quantity' => -$item['quantity'],
                            'type' => 'sale',
                            'reference_type' => 'Sale',
                            'reference_id' => $sale->id,
                            'notes' => 'Stock updated via sale invoice: ' . $sale->invoice_number,
                        ]);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $item['warehouse_id'],
                            'quantity' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                            'total_price' => $item['total_price'],
                        ]);
                    }
                } else {
                    $sale->update($updateData);
                }

                return response()->json([
                    'message' => 'Sale updated successfully',
                    'data' => $sale->load('items.product')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete Sale
     * 
     * Revert stock and soft delete.
     */
    public function destroy($id)
    {
        $sale = Sale::with('items')->findOrFail($id);

        return DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                $stock = Stock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->first();

                if ($stock) {
                    $stock->quantity += $item->quantity;
                    $stock->save();

                    // Record Deletion Movement
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $item->warehouse_id,
                        'quantity' => $item->quantity,
                        'type' => 'adjustment',
                        'reference_type' => 'Sale',
                        'reference_id' => $sale->id,
                        'notes' => 'Stock reverted due to sale deletion: ' . $sale->invoice_number,
                    ]);
                }
            }

            $sale->delete();

            return response()->json([
                'message' => 'Sale deleted successfully'
            ]);
        });
    }



    /**
     * Generate Invoice
     */
    public function invoice($id)
    {
        $sale = Sale::with(['items.product', 'items.warehouse'])->findOrFail($id);
        
        // POS-82 paper size: Width = 82mm (~232pt) for better margins, Height = Continuous (~600pt)
        $customPaper = [0, 0, 232.44, 600]; 
        $pdf = \PDF::loadView('pdf.sale_invoice', compact('sale'))
                  ->setPaper($customPaper);

        return $pdf->stream('Receipt_' . $sale->invoice_number . '.pdf');
    }
}