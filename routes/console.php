<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock:check-expiry', function () {
    $this->info('Checking for expiring stock...');
    
    $oneMonthFromNow = now()->addMonth();
    $today = now()->startOfDay();

    $expiringStocks = \App\Models\Stock::with(['product', 'warehouse'])
        ->where('expiry_date', '>=', $today)
        ->where('expiry_date', '<=', $oneMonthFromNow)
        ->where('quantity', '>', 0)
        ->get();

    if ($expiringStocks->isEmpty()) {
        $this->info('No expiring stock found.');
        return;
    }

    $users = \App\Models\User::all();
    
    foreach ($expiringStocks as $stock) {
        \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\ExpiryNotification($stock));
    }

    $this->info("Notifications sent for {$expiringStocks->count()} batches.");
})->purpose('Check for stock expiring within one month and notify users')->daily();

\Illuminate\Support\Facades\Schedule::command('stock:process-expired')->daily();

