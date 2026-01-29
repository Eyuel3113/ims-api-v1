<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Purchase extends Model
{
    use SoftDeletes, LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['invoice_number', 'supplier_id', 'supplier_name', 'purchase_date', 'status', 'total_amount', 'tax_amount', 'grand_total', 'notes', 'is_active'];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($m) => $m->id = (string) Str::uuid());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }
}