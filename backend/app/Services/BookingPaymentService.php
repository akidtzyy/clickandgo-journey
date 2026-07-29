<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CarRental;
use App\Models\Customer;
use App\Models\TourPackage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookingPaymentService
{
    private string $serverKey;
    private string $snapBaseUrl;
    private string $apiBaseUrl;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key');
        $isProduction = config('services.midtrans.is_production', false);
        $this->snapBaseUrl = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $this->apiBaseUrl = $isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Recalculate the correct total_price from database for a given booking type.
     * The server NEVER trusts client-supplied price.
     *
     * @param string $bookingType  'package' or 'car_rental'
     * @param int    $itemId       The ID of the TourPackage or CarRental
     * @param int    $quantity     Number of pax (package) or rental days (car_rental)
     * @return float               The verified server-side price
     * @throws \RuntimeException   If the item cannot be found
     */
    public function calculatePrice(string $bookingType, int $itemId, int $quantity = 1): float
    {
        if ($bookingType === 'package') {
            $item = TourPackage::active()->findOrFail($itemId);
        } elseif ($bookingType === 'car_rental') {
            $item = CarRental::available()->findOrFail($itemId);
        } else {
            throw new \RuntimeException("Unknown booking_type: {$bookingType}");
        }

        return (float) $item->price * max(1, $quantity);
    }

    /**
     * Create or refresh a Midtrans Snap token for a booking.
     * Supports both initial (FULL/DP) and final pelunasan (FINAL) transactions.
     *
     * @param Booking $booking
     * @param bool    $isFinalPayment  If true, generates a FINAL pelunasan token
     * @return array  ['snap_token' => string, 'order_id' => string, 'payment_url' => string]
     * @throws \RuntimeException
     */
    public function createSnapToken(Booking $booking, bool $isFinalPayment = false): array
    {
        $timestamp = now()->timestamp;
        $suffix = $isFinalPayment ? "FINAL-{$timestamp}" : "INITIAL-{$timestamp}";
        $orderId = "TRAVEL-{$booking->id}-{$suffix}";

        // Determine gross_amount: 50% for DP initial, or the remaining_balance for pelunasan
        if ($isFinalPayment) {
            $grossAmount = (int) $booking->remaining_balance;
            $itemDescription = "Pelunasan: {$booking->item_name}";
        } elseif ($booking->payment_type === 'DP') {
            $grossAmount = (int) ceil($booking->total_price / 2);
            $itemDescription = "DP 50%: {$booking->item_name}";
        } else {
            $grossAmount = (int) $booking->total_price;
            $itemDescription = $booking->item_name;
        }

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $customer = $booking->customer;
        $payload = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => [[
                'id'       => $booking->id,
                'price'    => $grossAmount,
                'quantity' => 1,
                'name'     => $itemDescription,
            ]],
            'customer_details' => [
                'first_name' => $customer->name,
                'email'      => $customer->email,
                'phone'      => $customer->phone,
            ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit'       => 'minutes',
                'duration'   => 30,
            ],
            'callbacks' => [
                'finish'   => "{$frontendUrl}/?show_bookings=true",
                'unfinish' => "{$frontendUrl}/?show_bookings=true",
                'error'    => "{$frontendUrl}/?show_bookings=true",
            ],
        ];

        $response = Http::withBasicAuth($this->serverKey, '')
            ->post($this->snapBaseUrl, $payload);

        if ($response->failed()) {
            Log::error('Midtrans Snap token error', [
                'booking_id' => $booking->id,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create Midtrans Snap token: ' . $response->body());
        }

        $data = $response->json();
        $snapToken = $data['token'] ?? null;
        $paymentUrl = $data['redirect_url'] ?? null;

        if (! $snapToken) {
            throw new \RuntimeException('Midtrans returned no token.');
        }

        // Persist token and order_id into the booking record
        if ($isFinalPayment) {
            $booking->update([
                'final_snap_token'   => $snapToken,
                'final_order_id'     => $orderId,
                'final_payment_link' => $paymentUrl,
            ]);
        } else {
            $booking->update([
                'snap_token'   => $snapToken,
                'order_id'     => $orderId,
                'payment_link' => $paymentUrl,
            ]);
        }

        return [
            'snap_token'  => $snapToken,
            'order_id'    => $orderId,
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Verify the Midtrans webhook signature.
     * Algorithm: SHA512(order_id + status_code + gross_amount + server_key)
     *
     * @param array $payload  Decoded Midtrans webhook POST body
     * @return bool
     */
    public function verifySignature(array $payload): bool
    {
        $expected = hash('sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            $this->serverKey
        );

        return hash_equals($expected, $payload['signature_key'] ?? '');
    }

    /**
     * Process a Midtrans payment notification (webhook) and update booking + customer stats.
     *
     * @param array $payload  Verified Midtrans notification payload
     * @return Booking        The updated booking
     * @throws \RuntimeException
     */
    public function processWebhookNotification(array $payload): Booking
    {
        $orderId        = $payload['order_id'];
        $transactionStatus = $payload['transaction_status'];
        $fraudStatus    = $payload['fraud_status'] ?? null;
        $grossAmount    = (float) ($payload['gross_amount'] ?? 0);

        // Determine if it's an INITIAL (DP) or FINAL (pelunasan) order
        $isFinalPayment = str_contains($orderId, '-FINAL-');

        // Extract numeric booking ID from order_id: TRAVEL-{id}-INITIAL/FINAL-{ts}
        preg_match('/^TRAVEL-(\d+)-/', $orderId, $matches);
        $bookingId = $matches[1] ?? null;

        if (! $bookingId) {
            throw new \RuntimeException("Could not parse booking ID from order_id: {$orderId}");
        }

        $booking = Booking::findOrFail($bookingId);

        $isSettled = in_array($transactionStatus, ['settlement', 'capture'])
            && ($fraudStatus === null || $fraudStatus === 'accept');

        if ($isSettled) {
            if ($isFinalPayment || $booking->payment_type === 'FULL') {
                // Full payment or pelunasan settled
                $booking->update([
                    'payment_status'    => 'paid',
                    'amount_paid'       => $booking->total_price,
                    'remaining_balance' => 0,
                    'paid_at'           => now(),
                ]);
            } else {
                // DP initial payment settled
                $dpAmount        = ceil($booking->total_price / 2);
                $remainingBalance = $booking->total_price - $dpAmount;
                $booking->update([
                    'payment_status'    => 'partially_paid',
                    'amount_paid'       => $dpAmount,
                    'remaining_balance' => $remainingBalance,
                    'paid_at'           => now(),
                ]);
            }

            // Update customer stats
            $booking->customer->recordPayment($grossAmount);

        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            // Only update to failed if this is NOT a final payment attempt of a partially paid booking
            if (!$isFinalPayment && $booking->payment_status !== 'partially_paid' && $booking->payment_status !== 'paid') {
                $booking->update([
                    'payment_status' => 'failed',
                ]);
            }
        } elseif ($transactionStatus === 'pending') {
            // Only update to pending if this is NOT a final payment attempt of a partially paid booking
            if (!$isFinalPayment && $booking->payment_status !== 'partially_paid' && $booking->payment_status !== 'paid') {
                $booking->update([
                    'payment_status' => 'pending',
                ]);
            }
        }

        return $booking->fresh();
    }

    /**
     * Query Midtrans API for the real-time transaction status of an order.
     *
     * @param string $orderId  The Midtrans order_id to verify
     * @return array           Raw Midtrans status response JSON
     * @throws \RuntimeException
     */
    public function verifyTransactionStatus(string $orderId): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->get("{$this->apiBaseUrl}/{$orderId}/status");

        if ($response->failed()) {
            throw new \RuntimeException("Midtrans status check failed: " . $response->body());
        }

        $data = $response->json();

        // Auto-update database with status from Midtrans
        try {
            $this->processWebhookNotification($data);
        } catch (\Throwable $e) {
            Log::error('Failed to auto-update booking during status verification', ['error' => $e->getMessage()]);
        }

        return $data;
    }
}
