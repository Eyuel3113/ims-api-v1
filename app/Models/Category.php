<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Category extends Model
{
    use SoftDeletes, LogsActivity;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'code', 'description', 'is_active'];

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
            ->setDescriptionForEvent(fn(string $eventName) => "Category has been {$eventName}");
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}