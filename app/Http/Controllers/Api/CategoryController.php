<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Categories
 * APIs for managing product categories
 */
class CategoryController extends Controller
{
    /**
     * List Categories
     * 
     * Get paginated list of categories with optional search and filter.
     * 
     * @queryParam search string optional Search by name or code or description.
     * @queryParam status string optional active /inactive.
     * @queryParam limit integer optional Items per page. Default 10.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status'); // 'active', 'inactive', or empty for all
        $limit = $request->query('limit', 10);

        $query = Category::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'message' => 'Categories fetched successfully',
            'data' => $categories->items(),
            'pagination' => [
                'total' => $categories->total(),
                'per_page' => $categories->perPage(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
            ]
        ]);
    }

    /**
     * Create Category
     * 
     * Add a new category.
     * 
     * @bodyParam name string required Category name.
     * @bodyParam code string required Unique code.
     * @bodyParam description string optional Description.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
            'code' => 'required|string|unique:categories,code|max:50',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Get Category
     * 
     * Show single category.
     * 
     * @urlParam id string required Category UUID.
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'message' => 'Category retrieved successfully',
            'data' => $category
        ]);
    }

    /**
     * Update Category
     * 
     * Update category details.
     * 
     * @urlParam id string required Category UUID.
     * @bodyParam name string optional
     * @bodyParam code string optional Must be unique
     * @bodyParam description string optional
     * @bodyParam is_active boolean optional
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($id)],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('categories', 'code')->ignore($id)],
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Toggle Category Active/Inactive
     *
     * Toggle the active status of a category.
     *
     * @urlParam id string required The UUID of the category.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->is_active = !$category->is_active;
        $category->save();

        return response()->json([
            'message'   => 'Category visibility updated successfully',
            'is_active' => $category->is_active,
            'data'      => $category
        ]);
    }



    /**
     * List Active Categories
     *
     * Display a listing of active categories (for public view) without pagination.
     * @queryParam search string optional Filter active categories by name or description.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activeCategories(Request $request)
    {
        $search = $request->query('search');

        $query = Category::active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orwhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $categories = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Active categories fetched successfully',
            'data'    => $categories
        ]);
    }

    /**
     * Delete Category
     * 
     * Soft delete category.
     * 
     * @urlParam id string required Category UUID.
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}