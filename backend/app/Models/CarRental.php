<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarRental extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'price',
        'duration_desc',
        'capacity',
        'image_url',
        'category',
        'features',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_available' => 'boolean',
        ];
    }

    // Scope to only retrieve available cars
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
