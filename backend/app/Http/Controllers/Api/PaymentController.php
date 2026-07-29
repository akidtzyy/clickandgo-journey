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
}
