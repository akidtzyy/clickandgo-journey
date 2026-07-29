<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CancelExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:cancel-expired';

    /**
     * The console command description.
     */
    protected $description = 'Automatically mark pending bookings with past expiry_time as expired.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = Booking::whereIn('payment_status', ['unpaid', 'pending'])
            ->where('expiry_time', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired bookings found.');
            return self::SUCCESS;
        }

        $ids = $expired->pluck('id')->toArray();

        Booking::whereIn('id', $ids)->update([
            'status'         => 'expired',
            'payment_status' => 'expired',
        ]);

        $count = count($ids);
        $this->info("Expired {$count} booking(s): " . implode(', ', $ids));
        Log::info("CancelExpiredBookings: expired {$count} booking(s)", ['ids' => $ids]);

        return self::SUCCESS;
    }
}
