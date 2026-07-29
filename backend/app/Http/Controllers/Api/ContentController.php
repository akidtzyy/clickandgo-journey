<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    /**
     * GET /api/v1/destinations
     */
    public function destinations(): JsonResponse
    {
        return response()->json(['data' => Destination::all()]);
    }

    /**
     * GET /api/v1/faqs
     */
    public function faqs(): JsonResponse
    {
        return response()->json(['data' => Faq::ordered()->get()]);
    }

    /**
     * GET /api/v1/testimonials
     */
    public function testimonials(): JsonResponse
    {
        return response()->json(['data' => Testimonial::all()]);
    }
}
