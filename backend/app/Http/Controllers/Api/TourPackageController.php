<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TourPackage;
use App\Http\Requests\StoreTourPackageRequest;
use Illuminate\Http\JsonResponse;

class TourPackageController extends Controller
{
    /**
     * GET /api/v1/tour-packages
     * Public: list all active packages.
     */
    public function index(): JsonResponse
    {
        $packages = TourPackage::all(); // Admin can see inactive packages too

        $mapped = $packages->map(function ($p) {
            return [
                'id'           => $p->id,
                'name'         => $p->name,
                'description'  => $p->description,
                'duration'     => $p->duration,
                'price'        => (float) $p->price,
                'highlights'   => $p->highlights,
                'included'     => $p->included,
                'category'     => $p->category,
                'image_url'    => $p->image_url,
                'is_available' => (bool) $p->is_available,
            ];
        });

        return response()->json(['data' => $mapped]);
    }

    /**
     * POST /api/v1/tour-packages
     * Admin: create a new tour package.
     */
    public function store(StoreTourPackageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $package = TourPackage::create($validated);

        return response()->json([
            'message' => 'Tour package created successfully.',
            'data'    => $package,
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/tour-packages/{id}
     * Admin: update a tour package.
     */
    public function update(\Illuminate\Http\Request $request, $id): JsonResponse
    {
        $package = TourPackage::findOrFail($id);

        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'description'  => ['sometimes', 'string'],
            'duration'     => ['sometimes', 'string', 'max:100'],
            'price'        => ['sometimes', 'numeric', 'min:0'],
            'highlights'   => ['sometimes', 'array'],
            'included'     => ['sometimes', 'array'],
            'category'     => ['sometimes', 'string', 'max:100'],
            'image_url'    => ['nullable', 'string'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $package->update($validated);

        return response()->json([
            'message' => 'Tour package updated successfully.',
            'data'    => $package,
        ]);
    }

    /**
     * DELETE /api/v1/tour-packages/{id}
     * Admin: delete a tour package.
     */
    public function destroy($id): JsonResponse
    {
        $package = TourPackage::findOrFail($id);
        $package->delete();

        return response()->json([
            'message' => 'Tour package deleted successfully.'
        ]);
    }
}

