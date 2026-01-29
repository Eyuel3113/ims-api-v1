<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Category;
use Illuminate\Support\Str;

class PurchaseVatTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_calculates_vat_correctly()
    {
        // 1. Create dependencies
        $category = Category::firstOrCreate(
            ['name' => 'Test Category'],
            ['description' => 'Test', 'is_active' => true]
        );

        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-TEST'],
            ['name' => 'Test Warehouse', 'is_active' => true]
        );

        $supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-TEST'],
            ['name' => 'Test Supplier', 'contact_person' => 'Test', 'phone' => '123456']
        );

        // 2. Create Products
        $vatableProduct = Product::create([
            'name' => 'Vatable Product ' . Str::random(5),
            'code' => 'VP-' . Str::random(5),
            'category_id' => $category->id,
            'unit' => 'pcs',
            'purchase_price' => 100,
            'selling_price' => 150,
            'is_vatable' => true,
        ]);

        $nonVatableProduct = Product::create([
            'name' => 'Non Vatable Product ' . Str::random(5),
            'code' => 'NVP-' . Str::random(5),
            'category_id' => $category->id,
            'unit' => 'pcs',
            'purchase_price' => 100,
            'selling_price' => 150,
            'is_vatable' => false,
        ]);

        // 3. Make Purchase Request via API (or direct controller call if Auth middleware blocks)
        // Assuming API is open or we can actAs user. 
        // For simplicity, we can test the logic by hitting the endpoint.
        // We'll catch exceptions if middleware fails, but usually local dev environments might be open or we can try.
        
        $invoiceNumber = 'INV-' . Str::random(10);
        
        $response = $this->postJson('/api/purchases', [
            'invoice_number' => $invoiceNumber,
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'product_id' => $vatableProduct->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 2, // Total 200. Tax 15% of 200 = 30.
                    'unit_price' => 100
                ],
                [
                    'product_id' => $nonVatableProduct->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => 1, // Total 100. Tax 0.
                    'unit_price' => 100
                ]
            ]
        ]);

        // 4. Assertions
        $response->assertStatus(201);
        
        $data = $response->json('data');
        
        // Total Amount: 200 + 100 = 300
        // Tax Amount: 30 + 0 = 30
        // Grand Total: 300 + 30 = 330
        
        $this->assertEquals(300, $data['total_amount'], 'Total Amount Mismatch');
        $this->assertEquals(30, $data['tax_amount'], 'Tax Amount Mismatch');
        $this->assertEquals(330, $data['grand_total'], 'Grand Total Mismatch');
        
        // Clean up (optional if using RefreshDatabase, but here we manually created stuff)
        // $vatableProduct->delete();
        // $nonVatableProduct->delete();
        // Purchase::find($data['id'])->delete(); 
    }
}
