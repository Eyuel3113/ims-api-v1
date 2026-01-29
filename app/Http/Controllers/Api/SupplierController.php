<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Suppliers
 * APIs for managing suppliers
 */
class SupplierController extends Controller
{
    /**
     * List Suppliers
     * 
     * Get paginated list of suppliers.
     * 
     * @queryParam search string optional Search by name, code, phone, email.
     * @queryParam status string optional filter by active/inactive.
     * @queryParam limit integer optional Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $limit = $request->query('limit', 10);

        $query = Supplier::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Suppliers fetched successfully',
            'data' => $suppliers->items(),
            'pagination' => [
                'total' => $suppliers->total(),
                'per_page' => $suppliers->perPage(),
                'current_page' => $suppliers->currentPage(),
                'last_page' => $suppliers->lastPage(),
            ]
        ]);
    }

    /**
     * Create Supplier
     * 
     * Add new supplier.
     * 
     * @bodyParam name string required
     * @bodyParam code string required Unique
     * @bodyParam phone string optional
     * @bodyParam email string optional
     * @bodyParam address string optional
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:suppliers,code|max:50',
            'phone' => 'nullable|string|unique:suppliers,phone|max:20',
            'email' => 'nullable|email|unique:suppliers,email',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => $supplier
        ], 201);
    }

    /**
     * Get Supplier
     * 
     * Show single supplier.
     * 
     * @urlParam id string required Supplier UUID.
     */
    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'message' => 'Supplier retrieved successfully',
            'data' => $supplier
        ]);
    }

    /**
     * Update Supplier
     * 
     * Update supplier details.
     * 
     * @urlParam id string required Supplier UUID.
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('suppliers', 'code')->ignore($id)],
            'phone' => ['nullable', 'string', Rule::unique('suppliers', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', Rule::unique('suppliers', 'email')->ignore($id)],
            'address' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $supplier->update($validated);

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data' => $supplier
        ]);
    }

    /**
     * Delete Supplier
     * 
     * Soft delete supplier.
     * 
     * @urlParam id string required Supplier UUID.
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully']);
    }

    /**
     * Toggle Supplier Status
     */
    public function toggleStatus($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->is_active = !$supplier->is_active;
        $supplier->save();

        return response()->json([
            'message' => 'Supplier visibility updated successfully',
            'is_active' => $supplier->is_active,
            'data' => $supplier
        ]);
    }

    /**
     * List Active Suppliers
     * @queryParam search string optional Search by name, code, phone, email.
     */
    public function activeSuppliers(Request $request)
    {
        $search = $request->query('search');

        $query = Supplier::active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Active suppliers fetched successfully',
            'data' => $suppliers
        ]);
    }
}