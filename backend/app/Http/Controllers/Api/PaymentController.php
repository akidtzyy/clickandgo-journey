<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private BookingPaymentService $paymentService)
    {
    }

    /**
     * POST /api/v1/payments/snap-token
     * Protected: generate a Midtrans Snap token for a booking.
     * Supports both initial (DP 50% or FULL) and final pelunasan payment flows.
     */
    public function snapToken(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id'       => ['required', 'integer', 'exists:bookings,id'],
            'is_final_payment' => ['boolean'],
        ]);

        $booking = Booking::with('customer')->findOrFail($request->integer('booking_id'));

        // Security: ensure authenticated user owns this booking
        $authUser = $request->user();
        if ($authUser && ! $authUser->isAdmin()) {
            if ($booking->customer->user_id !== $authUser->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        // Prevent re-paying an already-paid booking
        if ($booking->payment_status === 'paid') {
            return response()->json(['message' => 'Booking ini sudah lunas.'], 409);
        }

        $isFinalPayment = $request->boolean('is_final_payment', false);

        try {
            $result = $this->paymentService->createSnapToken($booking, $isFinalPayment);

            return response()->json([
                'message'     => 'Snap token berhasil dibuat.',
                'data'        => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Snap token generation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal membuat token pembayaran.'], 500);
        }
    }

    /**
     * POST /api/v1/payments/webhook
     * Public (no auth): Midtrans payment notification callback.
     * Verifies SHA512 signature before processing.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Step 1: Verify Midtrans signature
        if (! $this->paymentService->verifySignature($payload)) {
            Log::warning('Midtrans webhook signature mismatch', ['payload' => $payload]);
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        try {
            $booking = $this->paymentService->processWebhookNotification($payload);

            return response()->json([
                'message' => 'Notifikasi berhasil diproses.',
                'status'  => $booking->payment_status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Webhook processing error.'], 500);
        }
    }

    /**
     * POST /api/v1/payments/verify-status
     * Protected: query Midtrans API for real-time transaction status.
     */
    public function verifyStatus(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'string'],
        ]);

        $orderId = $request->string('order_id');

        // Auto-promote initial order ID to final order ID if the booking is fully paid via DP
        preg_match('/^TRAVEL-(\d+)-/', $orderId, $matches);
        $bookingId = $matches[1] ?? null;

        if ($bookingId) {
            $booking = Booking::find($bookingId);
            if ($booking && str_contains($orderId, '-INITIAL-') && $booking->payment_status === 'paid' && $booking->final_order_id) {
                Log::info('PaymentController: Promoting status verification to final payment order ID', [
                    'booking_id' => $booking->id,
                    'original_order_id' => $orderId,
                    'promoted_order_id' => $booking->final_order_id,
                ]);
                $orderId = $booking->final_order_id;
            }
        }

        try {
            $status = $this->paymentService->verifyTransactionStatus($orderId);

            return response()->json([
                'message' => 'Status pembayaran berhasil diverifikasi.',
                'data'    => $status,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment status verification failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal memverifikasi status pembayaran.'], 500);
        }
    }
    /**
     * POST /api/v1/payments/verify-and-sync
     * Protected: query Midtrans for real-time status AND immediately update the booking DB record.
     * Used by frontend polling after pelunasan payment.
     */
    public function verifyAndSync(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
        ]);

        $booking = Booking::with('customer')->findOrFail($request->integer('booking_id'));

        // Determine which order_id to check: prefer final_order_id for DP bookings with pelunasan
        $orderIdToCheck = $booking->final_order_id ?: $booking->order_id;

        if (!$orderIdToCheck) {
            return response()->json(['message' => 'No transaction found for this booking.'], 404);
        }

        try {
            $status = $this->paymentService->verifyTransactionStatus($orderIdToCheck);
            $transactionStatus = $status['transaction_status'] ?? null;
            $fraudStatus = $status['fraud_status'] ?? null;
            $isFinalPayment = str_contains($orderIdToCheck, '-FINAL-');

            $isSettled = in_array($transactionStatus, ['settlement', 'capture'])
                && ($fraudStatus === null || $fraudStatus === 'accept');

            $updated = false;

            if ($isSettled) {
                if ($isFinalPayment || $booking->payment_type === 'FULL') {
                    // Final payment or full payment settled
                    if ($booking->payment_status !== 'paid') {
                        $booking->update([
                            'payment_status'    => 'paid',
                            'amount_paid'       => $booking->total_price,
                            'remaining_balance' => 0,
                            'paid_at'           => now(),
                        ]);
                        // Also update customer stats
                        if ($booking->customer) {
                            $booking->customer->recordPayment((float) ($status['gross_amount'] ?? 0));
                        }
                        $updated = true;
                    }
                } else {
                    // DP initial payment settled — mark as partially paid
                    if ($booking->payment_status !== 'partially_paid' && $booking->payment_status !== 'paid') {
                        $dpAmount = ceil($booking->total_price / 2);
                        $remaining = $booking->total_price - $dpAmount;
                        $booking->update([
                            'payment_status'    => 'partially_paid',
                            'amount_paid'       => $dpAmount,
                            'remaining_balance' => $remaining,
                            'paid_at'           => now(),
                        ]);
                        if ($booking->customer) {
                            $booking->customer->recordPayment((float) ($status['gross_amount'] ?? 0));
                        }
                        $updated = true;
                    }
                }
            }

            return response()->json([
                'message'            => $updated ? 'Status pembayaran berhasil diperbarui.' : 'Status tidak berubah.',
                'payment_status'     => $booking->fresh()->payment_status,
                'transaction_status' => $transactionStatus,
                'updated'            => $updated,
            ]);
        } catch (\Throwable $e) {
            Log::error('Verify-and-sync failed', ['error' => $e->getMessage(), 'booking_id' => $booking->id]);
            return response()->json(['message' => 'Gagal memeriksa status pembayaran.'], 500);
        }
    }
}
