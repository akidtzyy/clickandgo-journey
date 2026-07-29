<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\BookingPaymentService;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        private BookingPaymentService $paymentService,
        private CloudinaryService $cloudinary,
    ) {}

    /**
     * POST /api/v1/bookings
     * Create a new booking with server-side price verification.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $booking = DB::transaction(function () use ($data, $request) {
                // 1. Server-side price calculation — do NOT trust client input
                $quantity  = $data['quantity'] ?? 1;
                $totalPrice = $this->paymentService->calculatePrice(
                    $data['booking_type'],
                    $data['item_id'],
                    $quantity
                );

                // 2. Find or create / update customer record
                // Search including soft-deleted records
                $customer = Customer::withTrashed()->where('email', $data['email'])->first();

                Log::info('BookingController: Processing customer documents', [
                    'email' => $data['email'],
                    'customer_exists' => !is_null($customer),
                    'existing_identity_photo' => $customer?->identity_photo_path,
                    'existing_sim_photo' => $customer?->sim_idp_photo_path,
                    'has_ktp_file' => $request->hasFile('ktp_passport_file'),
                    'has_sim_file' => $request->hasFile('sim_idp_file'),
                ]);

                // Base customer data (no document paths yet)
                $customerData = [
                    'name'             => $data['name'],
                    'phone'            => preg_replace('/\D/', '', $data['phone']),
                    'nationality_type' => $data['nationality_type'],
                    'identity_type'    => $data['identity_type'],
                    'identity_number'  => $data['identity_number'] ?? null,
                    'user_id'          => $request->user()?->id,
                ];

                // Upload KTP / Passport ke Cloudinary (only if a new file was provided)
                if ($request->hasFile('ktp_passport_file')) {
                    $file = $request->file('ktp_passport_file');
                    Log::info('BookingController: Uploading KTP file to Cloudinary', [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    $url = $this->cloudinary->upload($file, 'identities/ktp');
                    Log::info('BookingController: KTP upload result', ['url' => $url]);
                    if ($url) {
                        $customerData['identity_photo_path']         = $url;
                        $customerData['identity_verification_status'] = 'UNVERIFIED';
                    }
                } elseif (!$customer || !$customer->identity_photo_path) {
                    // No existing doc — mark as unverified
                    $customerData['identity_verification_status'] = 'UNVERIFIED';
                }
                // If customer already has a doc and no new upload → leave both path + status untouched

                // Upload SIM / IDP ke Cloudinary (only if a new file was provided)
                if ($request->hasFile('sim_idp_file')) {
                    $file = $request->file('sim_idp_file');
                    Log::info('BookingController: Uploading SIM file to Cloudinary', [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    $url = $this->cloudinary->upload($file, 'identities/sim');
                    Log::info('BookingController: SIM upload result', ['url' => $url]);
                    if ($url) {
                        $customerData['sim_idp_photo_path'] = $url;
                    }
                }

                Log::info('BookingController: Upserting customer database record', [
                    'customer_id' => $customer?->id,
                    'customer_data' => $customerData
                ]);

                if ($customer) {
                    // Restore if soft-deleted, then update
                    if ($customer->trashed()) {
                        $customer->restore();
                    }
                    $customer->update($customerData);
                } else {
                    $customer = Customer::create(array_merge(['email' => $data['email']], $customerData));
                }

                // 3. Build booking record
                return Booking::create([
                    'booking_code'      => Booking::generateBookingCode(),
                    'customer_id'       => $customer->id,
                    'booking_type'      => $data['booking_type'],
                    'item_name'         => $data['item_name'] ?? "Item #{$data['item_id']}",
                    'date'              => $data['date'],
                    'duration'          => $data['duration'],
                    'notes'             => $data['notes'] ?? null,
                    'total_price'       => $totalPrice,
                    'payment_type'      => $data['payment_type'],
                    'amount_paid'       => 0,
                    'remaining_balance' => $totalPrice,
                    'status'            => 'pending',
                    'payment_status'    => 'unpaid',
                    'expiry_time'       => now()->addMinutes(30),
                ]);
            });

            return response()->json([
                'message' => 'Booking berhasil dibuat.',
                'data'    => $booking->load('customer'),
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Booking creation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal membuat booking: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/bookings
     * Admin: list all bookings.
     */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $bookings
        ]);
    }

    /**
     * PUT/PATCH /api/v1/bookings/{id}
     * Admin: update booking details or status.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        $data = $request->validate([
            'status'                => ['sometimes', 'string', 'in:pending,confirmed,paid,completed,cancelled,rescheduled,expired'],
            'payment_status'        => ['sometimes', 'string', 'in:unpaid,pending,partially_paid,paid,failed,expired,challenge'],
            'date'                  => ['sometimes', 'date'],
            'end_date'              => ['sometimes', 'date', 'nullable'],
            'duration'              => ['sometimes', 'string'],
            'notes'                 => ['sometimes', 'string', 'nullable'],
            'original_booking_date' => ['sometimes', 'date', 'nullable'],
            'reschedule_notes'      => ['sometimes', 'string', 'nullable'],
        ]);

        $booking->update($data);

        return response()->json([
            'message' => 'Booking berhasil diperbarui.',
            'data'    => $booking->load('customer')
        ]);
    }

    /**
     * DELETE /api/v1/bookings/{id}
     * Admin: delete a booking.
     */
    public function destroy($id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return response()->json([
            'message' => 'Booking berhasil dihapus.'
        ]);
    }

    /**
     * GET /api/v1/my-bookings
     * Protected: list all bookings belonging to the logged in user.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        // Find customer associated with user
        $customer = Customer::withTrashed()
            ->where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        if (!$customer) {
            return response()->json(['data' => []]);
        }

        $bookings = Booking::where('customer_id', $customer->id)
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $bookings]);
    }
}

