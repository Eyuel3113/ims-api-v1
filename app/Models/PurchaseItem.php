<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class PurchaseItem extends Model
{
    use LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'purchase_id', 'product_id', 'warehouse_id', 'quantity', 'unit_price', 'total_price', 'expiry_date'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
