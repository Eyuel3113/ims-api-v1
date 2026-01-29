<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class StockMovement extends Model
{
    use LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'type', 'reference_type', 'reference_id', 'notes', 'expiry_date'
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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
