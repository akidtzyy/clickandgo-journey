<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarRental;
use App\Http\Requests\StoreCarRentalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarRentalController extends Controller
{
    /**
     * GET /api/v1/car-rentals
     * Public: list cars, optionally filtered by type and availability.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CarRental::query();

        if ($request->has('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->boolean('is_available', true)) {
            $query->where('is_available', true);
        }

        $cars = $query->orderBy('price', 'asc')->get();

        $mapped = $cars->map(function ($car) {
            $features = $car->features;
            if (is_string($features)) {
                $decoded = json_decode($features, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $features = $decoded;
                }
            }
            if (empty($features)) {
                $features = ['AC', 'Audio System', 'Asuransi', 'Bersih & Nyaman'];
            }

            return [
                'id'            => $car->id,
                'name'          => $car->name,
                'type'          => $car->type,
                'seats'         => $car->capacity, // map capacity to seats
                'price'         => (float) $car->price,
                'is_available'  => (bool) $car->is_available,
                'image_url'     => $car->image_url,
                'duration_desc' => $car->duration_capacity ?? 'Per 24 Jam', // default fallback for frontend compat
                'category'      => $car->category ?? ($car->type === 'self_drive' ? 'Lepas Kunci' : 'Dengan Sopir'),
                'features'      => $features, // default fallback
            ];
        });

        return response()->json(['data' => $mapped]);
    }

    /**
     * POST /api/v1/car-rentals
     * Admin: add a new car to the fleet.
     */
    public function store(StoreCarRentalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Map seats/features if passed from frontend
        $capacity = $request->input('seats') ?? $validated['capacity'] ?? 4;

        $car = new CarRental();
        $car->name = $validated['name'];
        $car->type = $validated['type'];
        $car->capacity = $capacity;
        $car->price = $validated['price'];
        $car->is_available = $validated['is_available'] ?? true;
        $car->image_url = $validated['image_url'] ?? null;

        // Save new columns directly (bypassing fillable model restriction)
        $car->duration_capacity = $request->input('duration_capacity') ?? $request->input('duration_desc');
        $car->category = $request->input('category');

        $features = $request->input('features');
        if (is_array($features)) {
            $casts = $car->getCasts();
            $castType = $casts['features'] ?? null;
            if ($castType && in_array($castType, ['array', 'json', 'object', 'collection', 'encrypted:array', 'encrypted:json', 'encrypted:object', 'as_array', 'as_json', 'as_object', 'as_collection'])) {
                $car->features = $features;
            } else {
                $car->features = json_encode($features);
            }
        } else {
            $car->features = $features;
        }

        $car->save();

        return response()->json([
            'message' => 'Car rental added successfully.',
            'data'    => $car,
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/car-rentals/{id}
     * Admin: update a car rental.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $car = CarRental::findOrFail($id);

        $validated = $request->validate([
            'name'              => ['sometimes', 'string', 'max:255'],
            'type'              => ['sometimes', 'string', 'max:100'],
            'seats'             => ['sometimes', 'integer', 'min:1'],
            'capacity'          => ['sometimes', 'integer', 'min:1'],
            'price'             => ['sometimes', 'numeric', 'min:0'],
            'is_available'      => ['sometimes', 'boolean'],
            'image_url'         => ['nullable', 'string'],
            'duration_capacity' => ['nullable', 'string', 'max:255'],
            'duration_desc'     => ['nullable', 'string', 'max:255'],
            'category'          => ['nullable', 'string', 'max:255'],
            'features'          => ['nullable'],
        ]);

        $updateData = [];
        if (isset($validated['name']))         $updateData['name'] = $validated['name'];
        if (isset($validated['type']))         $updateData['type'] = $validated['type'];
        if (isset($validated['seats']))        $updateData['capacity'] = $validated['seats'];
        if (isset($validated['capacity']))     $updateData['capacity'] = $validated['capacity'];
        if (isset($validated['price']))        $updateData['price'] = $validated['price'];
        if (isset($validated['is_available'])) $updateData['is_available'] = $validated['is_available'];
        if (array_key_exists('image_url', $validated)) $updateData['image_url'] = $validated['image_url'];

        $car->fill($updateData);

        // Update new columns directly (bypassing fillable model restriction)
        if ($request->has('duration_capacity')) {
            $car->duration_capacity = $validated['duration_capacity'];
        } elseif ($request->has('duration_desc')) {
            $car->duration_capacity = $validated['duration_desc'];
        }

        if ($request->has('category')) {
            $car->category = $validated['category'];
        }

        if ($request->has('features')) {
            $features = $validated['features'];
            if (is_array($features)) {
                $casts = $car->getCasts();
                $castType = $casts['features'] ?? null;
                if ($castType && in_array($castType, ['array', 'json', 'object', 'collection', 'encrypted:array', 'encrypted:json', 'encrypted:object', 'as_array', 'as_json', 'as_object', 'as_collection'])) {
                    $car->features = $features;
                } else {
                    $car->features = json_encode($features);
                }
            } else {
                $car->features = $features;
            }
        }

        $car->save();

        return response()->json([
            'message' => 'Car rental updated successfully.',
            'data'    => $car,
        ]);
    }

    /**
     * DELETE /api/v1/car-rentals/{id}
     * Admin: delete a car rental.
     */
    public function destroy($id): JsonResponse
    {
        $car = CarRental::findOrFail($id);
        $car->delete();

        return response()->json([
            'message' => 'Car rental deleted successfully.'
        ]);
    }
}

