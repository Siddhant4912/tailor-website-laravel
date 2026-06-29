<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClothCategory;
use App\Models\Design;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class CatalogSystemController extends Controller
{
    use ApiResponse;
    // GET /catalog — all active categories with their active designs
    // public function index()
    // {
    //     try {
    //         $categories = ClothCategory::with([
    //             'designs' => fn($q) => $q->where('is_active', true),
    //         ])
    //             ->where('is_active', true)
    //             ->get();

    //         return $this->successResponse($categories, 'Catalog fetched');
    //     } catch (\Exception $e) {
    //         return $this->errorResponse('Failed to fetch catalog', 500, $e->getMessage());
    //     }
    // }

    public function index()
    {
        try {
            // Cache the catalog payload for 60 minutes to prevent massive database queries and timeouts
            $categories = \Illuminate\Support\Facades\Cache::remember('catalog_system_index', 3600, function () {
                return ClothCategory::where('is_active', true)->with([
                    'designs' => function ($query) {
                        $query->where('is_active', true)->with([
                            'garments' => function ($q) {
                                $q->where('is_active', true);
                            }
                        ]);
                    }
                ])->get();
            });

            return response()->json([
                'success' => true,
                'data' => $categories
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // GET /catalog/categories/{id}/garments — garments under a category
    public function garmentsByCategory($categoryId)
    {
        try {
            $category = ClothCategory::with([
                'garments' => fn($q) => $q->where('is_active', true)
                    ->with('measurements'),
            ])->findOrFail($categoryId);

            return $this->successResponse($category, 'Category garments fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Category not found', 404);
        }
    }

    // GET /catalog/designs/{id}/garments — garments under a design
    public function garmentsByDesign($designId)
    {
        try {
            $design = Design::with([
                'garments' => fn($q) => $q->where('is_active', true)
                    ->with('measurements'),
            ])->findOrFail($designId);

            return $this->successResponse($design, 'Design garments fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Design not found', 404);
        }
    }
}
