<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Warehouse extends Model
{
    use SoftDeletes, LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'code', 'address', 'phone', 'is_active'];

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
            ->logAll()
            ->setDescriptionForEvent(fn(string $eventName) => "Warehouse has been {$eventName}");
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function products()
    {
        return $this->hasManyThrough(Product::class, Stock::class, 'warehouse_id', 'id', 'id', 'product_id');
    }
}