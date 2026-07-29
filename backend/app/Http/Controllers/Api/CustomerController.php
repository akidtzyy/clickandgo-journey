<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * GET /api/v1/customers/search?query=
     * Admin: autocomplete search for customers by name, phone, or email.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->string('query')->trim();

        if ($query->isEmpty() || $query->length() < 2) {
            return response()->json(['data' => []]);
        }

        $customers = Customer::withTrashed(false)
            ->select(['id', 'name', 'email', 'phone', 'nationality_type', 'identity_verification_status', 'total_bookings'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return response()->json(['data' => $customers]);
    }

    /**
     * GET /api/v1/customers
     * Admin: list all customers.
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::orderBy('created_at', 'desc')->get();

        // Map for frontend compatibility
        $mapped = $customers->map(function ($c) {
            return [
                'id'                           => $c->id,
                'nik'                          => $c->identity_number,
                'full_name'                    => $c->name,
                'home_address'                 => $c->address,
                'phone'                        => $c->phone,
                'email'                        => $c->email,
                'nationality_type'             => $c->nationality_type,
                'identity_type'                => $c->identity_type,
                'identity_number'              => $c->identity_number,
                'country_origin'               => $c->country_origin,
                'identity_verification_status' => $c->identity_verification_status,
                // Accessor-resolved Cloudinary URLs (full https:// URLs)
                'ktp_passport_url'             => $c->ktp_passport_url,
                'sim_idp_url'                  => $c->sim_idp_url,
                'total_bookings'               => $c->total_bookings,
                'total_spent'                  => $c->total_spent,
                'last_booking_date'            => $c->last_booking_date,
                'created_at'                   => $c->created_at,
            ];
        });

        return response()->json(['data' => $mapped]);
    }

    /**
     * POST /api/v1/customers
     * Admin: create a customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name'                    => ['required', 'string', 'max:255'],
            'email'                        => ['required', 'email', 'unique:customers,email'],
            'phone'                        => ['required', 'string', 'unique:customers,phone'],
            'home_address'                 => ['nullable', 'string'],
            'nationality_type'             => ['sometimes', 'in:WNI,WNA'],
            'identity_type'                => ['sometimes', 'in:NIK,PASSPORT'],
            'identity_number'              => ['nullable', 'string'],
            'country_origin'               => ['nullable', 'string'],
            'identity_verification_status' => ['sometimes', 'in:UNVERIFIED,VERIFIED,EXPIRED'],
        ]);

        $customer = Customer::create([
            'name'                         => $validated['full_name'],
            'email'                        => $validated['email'],
            'phone'                        => preg_replace('/\D/', '', $validated['phone']),
            'address'                      => $validated['home_address'] ?? null,
            'nationality_type'             => $validated['nationality_type'] ?? 'WNI',
            'identity_type'                => $validated['identity_type'] ?? 'NIK',
            'identity_number'              => $validated['identity_number'] ?? null,
            'country_origin'               => $validated['country_origin'] ?? null,
            'identity_verification_status' => $validated['identity_verification_status'] ?? 'UNVERIFIED',
        ]);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data'    => $customer
        ], 201);
    }

    /**
     * PATCH/PUT /api/v1/customers/{id}
     * Admin: update a customer.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'full_name'                    => ['sometimes', 'string', 'max:255'],
            'email'                        => ['sometimes', 'email', 'unique:customers,email,' . $customer->id],
            'phone'                        => ['sometimes', 'string', 'unique:customers,phone,' . $customer->id],
            'home_address'                 => ['nullable', 'string'],
            'nationality_type'             => ['sometimes', 'in:WNI,WNA'],
            'identity_type'                => ['sometimes', 'in:NIK,PASSPORT'],
            'identity_number'              => ['nullable', 'string'],
            'country_origin'               => ['nullable', 'string'],
            'identity_verification_status' => ['sometimes', 'in:UNVERIFIED,VERIFIED,EXPIRED'],
        ]);

        $updateData = [];
        if (isset($validated['full_name']))                    $updateData['name'] = $validated['full_name'];
        if (isset($validated['email']))                        $updateData['email'] = $validated['email'];
        if (isset($validated['phone']))                        $updateData['phone'] = preg_replace('/\D/', '', $validated['phone']);
        if (array_key_exists('home_address', $validated))      $updateData['address'] = $validated['home_address'];
        if (isset($validated['nationality_type']))             $updateData['nationality_type'] = $validated['nationality_type'];
        if (isset($validated['identity_type']))                $updateData['identity_type'] = $validated['identity_type'];
        if (array_key_exists('identity_number', $validated))   $updateData['identity_number'] = $validated['identity_number'];
        if (array_key_exists('country_origin', $validated))    $updateData['country_origin'] = $validated['country_origin'];
        if (isset($validated['identity_verification_status'])) $updateData['identity_verification_status'] = $validated['identity_verification_status'];

        $customer->update($updateData);

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data'    => $customer
        ]);
    }

    /**
     * DELETE /api/v1/customers/{id}
     * Admin: delete a customer.
     */
    public function destroy($id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.'
        ]);
    }
}

