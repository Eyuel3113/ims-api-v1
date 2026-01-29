<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;



/**
 * @group Purchases
 * APIs for purchasing stock from suppliers
 */
class PurchaseController extends Controller
{
    /**
     * List Purchases
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Purchase::with(['supplier', 'items.product']);

        // Filtering by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Filtering by date range
        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        // Filtering by invoice number
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Search by invoice or item name
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $purchases = $query->orderBy('created_at', 'desc')
            ->paginate($limit);

        return response()->json([
            'message' => 'Purchases fetched successfully',
            'data' => $purchases->items(),
            'pagination' => [
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
            ]
        ]);
    }

    /**
     * List Active Purchases
     * 
     * Returns all active purchases without pagination.
     */
    public function activePurchases(Request $request)
    {
        $query = Purchase::with(['supplier', 'items.product'])->where('is_active', true);

        // Date range
        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        // Invoice search
        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        // Search by item name or invoice
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('invoice_number', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('items.product', function ($pq) use ($searchTerm) {
                      $pq->where('name', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        $purchases = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Active purchases fetched successfully',
            'data' => $purchases
        ]);
    }

    /**
     * Toggle Purchase Status
     */
    public function toggleStatus($id)
    {
        $purchase = Purchase::findOrFail($id);
        $purchase->is_active = !$purchase->is_active;
        $purchase->save();

        return response()->json([
            'message' => 'Purchase status toggled successfully',
            'data' => [
                'id' => $purchase->id,
                'is_active' => $purchase->is_active
            ]
        ]);
    }

    /**
     * Create Purchase
     * 
     * Add stock from supplier.
     * 
     * @bodyParam invoice_number string required
     * @bodyParam supplier_id string required
     * @bodyParam purchase_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam items.*.expiry_date date optional
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|string|unique:purchases,invoice_number',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            // First pass: collect products and calculate total
            $itemsWithPrices = [];
            $total = 0;
            $taxTotal = 0;

            foreach ($request->items as $itemData) {
                $product = \App\Models\Product::findOrFail($itemData['product_id']);
                $unitPrice = $itemData['unit_price'] ?? $product->purchase_price;
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

            $purchase = Purchase::create([
                'invoice_number' => $request->invoice_number,
                'supplier_id' => $request->supplier_id,
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'status' => $request->supplier_id ? 'pending' : 'received',
                'total_amount' => $total,
                'tax_amount' => $taxTotal,
                'grand_total' => $total + $taxTotal,
                'notes' => $request->notes,
            ]);

            foreach ($itemsWithPrices as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }

            if ($purchase->status === 'received') {
                $this->updateStockLevels($purchase);
            }

            return response()->json([
                'message' => $purchase->status === 'received' ? 'Purchase recorded and stock updated' : 'Purchase recorded as pending',
                'data' => $purchase->load('items.product', 'supplier')
            ], 201);
        });
    }

    /**
     * Mark Purchase as Received
     * 
     * Approves the purchase and updates stock levels.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function receiveStatus($id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        if ($purchase->status !== 'pending') {
            return response()->json([
                'message' => 'Purchase is already ' . $purchase->status
            ], 422);
        }

        return DB::transaction(function () use ($purchase) {
            $purchase->update(['status' => 'received']);
            $this->updateStockLevels($purchase);

            return response()->json([
                'message' => 'Purchase marked as received and stock updated',
                'data' => $purchase->load('items.product', 'supplier')
            ]);
        });
    }

    /**
     * Cancel Purchase
     * 
     * Marks a pending purchase as cancelled.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function cancelStatus($id)
    {
        $purchase = Purchase::findOrFail($id);

        if ($purchase->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending purchases can be cancelled'
            ], 422);
        }

        $purchase->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Purchase cancelled successfully',
            'data' => $purchase->load('items.product', 'supplier')
        ]);
    }

    /**
     * Get Purchase
     * 
     * Show single purchase with items.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'items.warehouse'])->findOrFail($id);

        return response()->json([
            'message' => 'Purchase retrieved successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Update Purchase
     * 
     * Update purchase details and adjust stock.
     * 
     * @urlParam id string required Purchase UUID
     * @bodyParam invoice_number string required
     * @bodyParam supplier_id string required
     * @bodyParam purchase_date date required
     * @bodyParam items array required
     * @bodyParam items.*.product_id string required
     * @bodyParam items.*.warehouse_id string required
     * @bodyParam items.*.quantity number required
     * @bodyParam items.*.expiry_date date optional
     */
    public function update(Request $request, $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        if ($purchase->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update a purchase that is already ' . $purchase->status
            ], 422);
        }

        $request->validate([
            'invoice_number' => 'sometimes|required|string|unique:purchases,invoice_number,' . $id,
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:255',
            'purchase_date' => 'sometimes|required|date',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $purchase) {
            $updateData = $request->only(['invoice_number', 'supplier_id', 'supplier_name', 'purchase_date', 'notes']);

            if ($request->has('items')) {
                // Since it's pending, no stock was ever added. No need to revert.
                
                // 1. Delete existing items
                $purchase->items()->delete();

                // 2. Fetch prices and calculate new total
                $itemsWithPrices = [];
                $total = 0;
                $taxTotal = 0;
                foreach ($request->items as $itemData) {
                    $product = \App\Models\Product::findOrFail($itemData['product_id']);
                    $unitPrice = $itemData['unit_price'] ?? $product->purchase_price;
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

                // 3. Update purchase top-level data
                $purchase->update($updateData);

                // 4. Create new items
                foreach ($itemsWithPrices as $item) {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);
                    // NO STOCK UPDATE FOR PENDING
                }
            } else {
                $purchase->update($updateData);
            }

            return response()->json([
                'message' => 'Purchase updated successfully',
                'data' => $purchase->load('items.product', 'supplier')
            ]);
        });
    }

    /**
     * Delete Purchase
     * 
     * Delete purchase and revert stock changes if it was received.
     * 
     * @urlParam id string required Purchase UUID
     */
    public function destroy($id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);

        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'received') {
                // Revert stock changes
                foreach ($purchase->items as $item) {
                    $stock = Stock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->where('expiry_date', $item->expiry_date)
                        ->first();

                    if ($stock) {
                        $stock->quantity -= $item->quantity;
                        $stock->save();

                        // Record Deletion Movement
                        StockMovement::create([
                            'product_id' => $item->product_id,
                            'warehouse_id' => $item->warehouse_id,
                            'quantity' => -$item->quantity,
                            'type' => 'adjustment',
                            'reference_type' => 'Purchase',
                            'reference_id' => $purchase->id,
                            'expiry_date' => $item->expiry_date,
                            'notes' => 'Stock adjustment due to purchase deletion: ' . $purchase->invoice_number,
                        ]);
                    }
                }
            }

            $purchase->delete();

            return response()->json([
                'message' => 'Purchase deleted successfully'
            ]);
        });
    }

    public function invoice($id)
    {
        $purchase = Purchase::with(['supplier', 'items.product', 'items.warehouse'])->findOrFail($id);

        $pdf = PDF::loadView('pdf.purchase_invoice', compact('purchase'));

        return $pdf->download('Purchase_' . $purchase->invoice_number . '.pdf');
    }

    /**
     * Update Stock Levels
     */
    private function updateStockLevels($purchase)
    {
        foreach ($purchase->items as $item) {
            // Add to stock
            $stock = Stock::firstOrNew([
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'expiry_date' => $item->expiry_date,
            ]);

            $stock->quantity = ($stock->quantity ?? 0) + $item->quantity;
            $stock->save();

            // Record Movement
            StockMovement::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $item->warehouse_id,
                'quantity' => $item->quantity,
                'type' => 'purchase',
                'reference_type' => 'Purchase',
                'reference_id' => $purchase->id,
                'expiry_date' => $item->expiry_date,
                'notes' => 'Stock received via invoice: ' . $purchase->invoice_number,
            ]);
        }
    }
}
