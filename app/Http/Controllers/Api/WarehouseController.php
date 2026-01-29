<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Warehouses
 * APIs for managing warehouses
 */
class WarehouseController extends Controller
{
    /**
     * List Warehouses
     * 
     * Get paginated list.
     * 
     * @queryParam search string optional Search by name or code.
     * @queryParam status string optional filter by active/inactive.
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Warehouse::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $warehouses = $query->withCount('stocks')->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Warehouses fetched successfully',
            'data' => $warehouses->items(),
            'pagination' => [
                'total' => $warehouses->total(),
                'per_page' => $warehouses->perPage(),
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
            ]
        ]);
    }

    /**
     * Create Warehouse
     * 
     * Add new warehouse.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:warehouses,code|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|unique:warehouses,phone|max:20',
        ]);

        $warehouse = Warehouse::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse
        ], 201);
    }

    /**
     * Get Warehouse
     */
    public function show(Request $request, $id)
    {
        $limit = $request->query('limit', 10);
        $warehouse = Warehouse::findOrFail($id);
        
        $stocks = $warehouse->stocks()
            ->with(['product'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return response()->json([
            'message' => 'Warehouse retrieved successfully',
            'data' => [
                'warehouse' => $warehouse->makeHidden('stocks'),
                'stocks' => $stocks->items(),
                'pagination' => [
                    'total' => $stocks->total(),
                    'per_page' => $stocks->perPage(),
                    'current_page' => $stocks->currentPage(),
                    'last_page' => $stocks->lastPage(),
                ]
            ]
        ]);
    }

    /**
     * Update Warehouse
     */
    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($id)],
            'address' => 'nullable|string',
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('warehouses', 'phone')->ignore($id)],
            'is_active' => 'sometimes|boolean',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse
        ]);
    }


    
    /**
     * Toggle Warehouse Active/Inactive
     *
     * Toggle the active status of a Warehouse.
     *
     * @urlParam id string required The UUID of the Warehouse.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        $Warehouse = Warehouse::findOrFail($id);
        $Warehouse->is_active = !$Warehouse->is_active;
        $Warehouse->save();

        return response()->json([
            'message'   => 'Category visibility updated successfully',
            'is_active' => $Warehouse->is_active,
            'data'      => $Warehouse
        ]);
    }



    /**
     * List Active Warehouse
     *
     * Display a listing of active Warehouse (for public view) without pagination.
     * @queryParam search string optional Filter active categories by name or description.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activeWarehouses(Request $request)
    {
        $search = $request->query('search');

        $query = Warehouse::active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orwhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $Warehouse = $query->withCount('stocks')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Active categories fetched successfully',
            'data'    => $Warehouse
        ]);
    }

    /**
     * Delete Warehouse
     */
    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->json(['message' => 'Warehouse deleted successfully']);
    }
}