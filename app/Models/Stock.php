<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Stock extends Model
{
    use LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'quantity', 'expiry_date'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['quantity', 'expiry_date'])
            ->setDescriptionForEvent(fn(string $eventName) => "Stock has been {$eventName}");
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