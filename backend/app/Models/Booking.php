<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_code',
        'customer_id',
        'booking_type',
        'item_name',
        'date',
        'duration',
        'notes',
        'total_price',
        'payment_type',
        'amount_paid',
        'remaining_balance',
        'status',
        'payment_status',
        'order_id',
        'snap_token',
        'payment_link',
        'final_order_id',
        'final_snap_token',
        'final_payment_link',
        'expiry_time',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'expiry_time' => 'datetime',
            'paid_at' => 'datetime',
            'total_price' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
        ];
    }

    // Relations
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Scopes for filtering
    public function scopeExpired($query)
    {
        return $query->whereIn('payment_status', ['unpaid', 'pending'])
            ->where('expiry_time', '<', now());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Generate a unique booking code in the format CGJ-YYYYMMDD-XXXX.
     */
    public static function generateBookingCode(): string
    {
        $date = now()->format('Ymd');
        
        // Count including soft-deleted rows to prevent duplicate codes when some are soft-deleted
        $todayCount = static::withTrashed()
            ->whereDate('created_at', now()->toDateString())
            ->count();
            
        $sequenceNum = $todayCount + 1;
        
        do {
            $sequence = str_pad($sequenceNum, 4, '0', STR_PAD_LEFT);
            $code = "CGJ-{$date}-{$sequence}";
            $sequenceNum++;
        } while (static::withTrashed()->where('booking_code', $code)->exists());

        return $code;
    }
}
