<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'address',
        'nationality_type',
        'identity_type',
        'identity_number',
        'country_origin',
        'identity_photo_path',
        'sim_idp_photo_path',
        'identity_verification_status',
        'total_bookings',
        'total_spent',
        'last_booking_date',
    ];

    protected $appends = [
        'ktp_passport_url',
        'sim_idp_url',
    ];

    public function getKtpPassportUrlAttribute(): ?string
    {
        if (!$this->identity_photo_path) return null;
        // Support both full URLs (Cloudinary) and legacy local paths
        return str_starts_with($this->identity_photo_path, 'http')
            ? $this->identity_photo_path
            : asset('storage/' . $this->identity_photo_path);
    }

    public function getSimIdpUrlAttribute(): ?string
    {
        if (!$this->sim_idp_photo_path) return null;
        // Support both full URLs (Cloudinary) and legacy local paths
        return str_starts_with($this->sim_idp_photo_path, 'http')
            ? $this->sim_idp_photo_path
            : asset('storage/' . $this->sim_idp_photo_path);
    }

    protected function casts(): array
    {
        return [
            'last_booking_date' => 'datetime',
            'total_spent' => 'decimal:2',
        ];
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Increment booking stats when a payment is settled.
     */
    public function recordPayment(float $amount): void
    {
        $this->increment('total_bookings');
        $this->increment('total_spent', $amount);
        $this->update(['last_booking_date' => now()]);
    }
}
