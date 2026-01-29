<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Expense Management
 * 
 * APIs for managing operational and capital expenses.
 */
class ExpenseController extends Controller
{
    /**
     * List Expenses
     * 
     * Get paginated list of expenses with filter and total sum.
     * 
     * @queryParam category string optional Operating Expenses, Non-Operating Expenses, Capital Expenses
     * @queryParam from_date date optional Filter by created date (from).
     * @queryParam to_date date optional Filter by created date (to).
     * @queryParam search string optional Title or description search.
     * @queryParam limit integer optional Default 10.
     * 
     * @response 200 {
     *   "message": "Expenses fetched successfully",
     *   "total_sum": 1500.00,
     *   "data": [ ... ],
     *   "pagination": { ... }
     * }
     */
    public function index(Request $request)
    {
        $query = Expense::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        $totalSum = (float) $query->sum('amount');

        $expenses = $query->with('user:id,name')->orderBy('created_at', 'desc')->paginate($request->limit ?? 10);

        return response()->json([
            'message' => 'Expenses fetched successfully',
            'total_sum' => $totalSum,
            'data' => $expenses->items(),
            'pagination' => [
                'total' => $expenses->total(),
                'per_page' => $expenses->perPage(),
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
            ]
        ]);
    }

    /**
     * List All by Category
     * 
     * Get all expenses without pagination, grouped by their category.
     * 
     * @urlParam category string optional Filter by specific category.
     * 
     * @response 200 {
     *   "message": "Expenses listed by category",
     *   "data": {
     *     "Operating Expenses": [ ... ],
     *     "Capital Expenses": [ ... ]
     *   }
     * }
     */
    public function listByCategory(Request $request)
    {
        $query = Expense::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses = $query->with('user:id,name')->get()->groupBy('category');

        return response()->json([
            'message' => 'Expenses listed by category',
            'data' => $expenses
        ]);
    }

    /**
     * Create Expenses
     * 
     * Record one or multiple expenses under a single category.
     * 
     * @bodyParam category string required Operating Expenses, Non-Operating Expenses, Capital Expenses. Example: Non-Operating Expenses
     * @bodyParam items object[] required The list of expenses to create.
     * @bodyParam items[].title string required Unique title for the expense. Example: Loan
     * @bodyParam items[].amount number required Amount spent. Example: 10000
     * @bodyParam items[].description string optional Description of the expense. Example: test expenses
     */
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string|in:Operating Expenses,Non-Operating Expenses,Capital Expenses',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255|unique:expenses,title',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        $createdExpenses = [];
        $category = $request->category;
        $userId = Auth::id();

        foreach ($request->items as $item) {
            $expense = Expense::create([
                'title' => $item['title'],
                'category' => $category,
                'amount' => $item['amount'],
                'description' => $item['description'] ?? null,
                'created_by' => $userId,
            ]);
            $createdExpenses[] = $expense;
        }

        return response()->json([
            'message' => count($createdExpenses) . ' expenses recorded successfully',
            'data' => $createdExpenses
        ], 201);
    }

    /**
     * Get Expense
     * 
     * @urlParam id string required Expense UUID
     */
    public function show($id)
    {
        $expense = Expense::with('user:id,name')->findOrFail($id);

        return response()->json([
            'message' => 'Expense retrieved successfully',
            'data' => $expense
        ]);
    }

    /**
     * Update Expense
     * 
     * @urlParam id string required Expense UUID
     * @bodyParam title string optional
     * @bodyParam category string optional Operating Expenses, Non-Operating Expenses, Capital Expenses
     * @bodyParam amount number optional
     * @bodyParam description string optional
     */
    public function update(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|in:Operating Expenses,Non-Operating Expenses,Capital Expenses',
            'amount' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $expense->update($request->all());

        return response()->json([
            'message' => 'Expense updated successfully',
            'data' => $expense->load('user:id,name')
        ]);
    }

    /**
     * Delete Expense
     * 
     * @urlParam id string required Expense UUID
     */
    public function destroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json([
            'message' => 'Expense deleted successfully'
        ]);
    }
}
