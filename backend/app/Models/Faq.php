<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'question', 'answer', 'category', 'order_index',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index', 'asc');
    }
}
