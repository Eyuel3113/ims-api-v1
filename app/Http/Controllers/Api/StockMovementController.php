<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;

/**
 * @group Stock History
 * Full audit trail of stock movements
 */
class StockMovementController extends Controller
{
    /**
     * List Stock Movements
     * 
     * @queryParam product_id string optional
     * @queryParam warehouse_id string optional
     * @queryParam type string optional purchase, sale, adjustment, damage, lost, found, opening_stock
     * @queryParam limit integer optional Default 20
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['product', 'warehouse'])
            ->orderBy('created_at', 'desc');

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $movements = $query->paginate($request->limit ?? 20);

        return response()->json([
            'message' => 'Stock movements fetched successfully',
            'data' => $movements->items(),
            'pagination' => [
                'total' => $movements->total(),
                'per_page' => $movements->perPage(),
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ]
        ]);
    }

    /**
     * Create Manual Stock Movement
     * 
     * Manually adjust stock for reasons like damage, loss, or found items.
     * 
     * @bodyParam product_id string required
     * @bodyParam warehouse_id string required
     * @bodyParam quantity number required Positive value.
     * @bodyParam type string required damage, lost, found, adjustment, opening_stock
     * @bodyParam notes string optional
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:damage,lost,found,adjustment,opening_stock',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
                $product = \App\Models\Product::findOrFail($request->product_id);
                $warehouse = \App\Models\Warehouse::findOrFail($request->warehouse_id);

                // Find or create stock record
                $stock = \App\Models\Stock::where('product_id', $request->product_id)
                    ->where('warehouse_id', $request->warehouse_id)
                    ->first();

                if (!$stock && in_array($request->type, ['found', 'adjustment', 'opening_stock'])) {
                     $stock = new \App\Models\Stock([
                        'product_id' => $request->product_id,
                        'warehouse_id' => $request->warehouse_id,
                        'quantity' => 0
                    ]);
                }

                if (!$stock) {
                     throw new \Exception("Stock record not found for this product in the specified warehouse.");
                }

                $qtyChange = $request->quantity;
                // If damage or lost, it's a reduction
                if (in_array($request->type, ['damage', 'lost'])) {
                    $qtyChange = -$request->quantity;
                }

                // Check for insufficient stock if reducing
                if ($qtyChange < 0 && ($stock->quantity + $qtyChange) < 0) {
                    throw new \Exception("Insufficient stock in this warehouse to record this " . $request->type);
                }

                $stock->quantity += $qtyChange;
                $stock->save();

                // Record movement
                $movement = StockMovement::create([
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => $qtyChange,
                    'type' => $request->type,
                    'reference_type' => 'Manual Adjustment',
                    'notes' => $request->notes,
                ]);

                // Check for low stock notification
                if ($qtyChange < 0) {
                    $totalStock = \App\Models\Stock::where('product_id', $product->id)->sum('quantity');
                    if ($product->min_stock > 0 && $totalStock <= $product->min_stock) {
                        $users = \App\Models\User::all();
                        \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\LowStockNotification($product, $totalStock));
                    }
                }

                return response()->json([
                    'message' => 'Stock movement recorded successfully',
                    'data' => $movement->load(['product', 'warehouse'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Product Movement History
     * 
     * Get paginated stock movements for a specific product with accurate running balance.
     * 
     * @urlParam id string required Product UUID
     * @queryParam warehouse_id string optional Filter by warehouse
     * @queryParam type string optional Filter by movement type
     * @queryParam limit integer optional Items per page. Default 15.
     */
    public function productMovementHistory(Request $request, $id)
    {
        $product = \App\Models\Product::findOrFail($id);
        $warehouseId = $request->warehouse_id;
        $type = $request->type;
        $limit = $request->limit ?? 10;

        // Base query for matching movements
        $query = StockMovement::with(['warehouse'])
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $movements = $query->paginate($limit);

        // To calculate balance accurately for each row in a paginated/filtered list:
        // We need the sum of all movements up to that specific movement's point in time.
        $history = collect($movements->items())->map(function ($m) use ($id, $warehouseId) {
            $in = $m->quantity > 0 ? $m->quantity : 0;
            $out = $m->quantity < 0 ? abs($m->quantity) : 0;
            
            // Calculate balance at this point in time
            // We use <= created_at to include this movement.
            // Note: If multiple movements have the exact same timestamp, 
            // the order might be non-deterministic unless we also account for ID or sequence.
            $balanceQuery = StockMovement::where('product_id', $id)
                ->where('created_at', '<=', $m->getRawOriginal('created_at') ?? $m->created_at);
            
            if ($warehouseId) {
                $balanceQuery->where('warehouse_id', $warehouseId);
            }
            
            $currentBalance = $balanceQuery->sum('quantity');

            return [
                'id' => $m->id,
                'date' => $m->created_at->toDateTimeString(),
                'type' => $m->type,
                'warehouse' => $m->warehouse->name ?? 'N/A',
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'in' => number_format((float)$in, 2, '.', ''),
                'out' => number_format((float)$out, 2, '.', ''),
                'balance' => number_format((float)$currentBalance, 2, '.', ''),
                'notes' => $m->notes,
            ];
        });

        return response()->json([
            'message' => 'Product stock movement history fetched successfully',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
            ],
            'data' => $history,
            'pagination' => [
                'total' => $movements->total(),
                'per_page' => $movements->perPage(),
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ]
        ]);
    }
}