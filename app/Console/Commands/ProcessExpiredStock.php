<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessExpiredStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:process-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finds expired stock, reduces quantity to 0, and records expired movement.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->format('Y-m-d');
        
        $expiredStocks = \App\Models\Stock::where('expiry_date', '<', $today)
            ->where('quantity', '>', 0)
            ->get();

        if ($expiredStocks->isEmpty()) {
            $this->info('No expired stock found.');
            return;
        }

        $this->info("Found {$expiredStocks->count()} expired stock items. Processing...");

        foreach ($expiredStocks as $stock) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($stock) {
                // Record movement
                \App\Models\StockMovement::create([
                    'product_id' => $stock->product_id,
                    'warehouse_id' => $stock->warehouse_id,
                    'quantity' => -$stock->quantity, // Negative quantity for removal
                    'type' => 'expired', 
                    'reference_type' => 'Stock Expiry',
                    'expiry_date' => $stock->expiry_date,
                    'notes' => "Stock expired on {$stock->expiry_date->format('Y-m-d')}. Auto-removed.",
                ]);

                // Zero out stock
                $stock->quantity = 0;
                $stock->save();
            });
            
            $this->info("Processed expired stock for Product ID: {$stock->product_id} in Warehouse ID: {$stock->warehouse_id}");
        }

        $this->info('Expired stock processing completed.');
    }
}
