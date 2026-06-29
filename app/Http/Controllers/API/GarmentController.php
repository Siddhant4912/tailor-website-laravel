<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Garments\GarmentRequest;
use App\Models\Garment;
use App\Services\GarmentService;

class GarmentController extends Controller
{

    protected $service;

    public function __construct(GarmentService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        try {
            $data = $this->service->list();
            return $this->successResponse($data, 'Garments fetched');
        } catch (\Exception $e) {
            // FIX: original had no try/catch — an exception would return an unformatted 500
            return $this->errorResponse('Failed to fetch garments', 500, $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $garment = $this->service->find($id);
            return $this->successResponse($garment, 'Garment details');
        } catch (\Exception $e) {
            return $this->errorResponse('Garment not found', 404);
        }
    }

    public function store(GarmentRequest $request)
    {
        try {
            $garment = $this->service->create($request->validated());
            return $this->successResponse($garment, 'Garment created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create garment', 500, $e->getMessage());
        }
    }

    public function update(GarmentRequest $request, Garment $garment)
    {
        try {
            $updated = $this->service->update($garment, $request->validated());
            return $this->successResponse($updated, 'Garment updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update garment', 500, $e->getMessage());
        }
    }

    public function destroy(Garment $garment)
    {
        try {
            $this->service->delete($garment);
            return $this->successResponse(null, 'Garment deleted');
        } catch (\Exception $e) {
            // FIX: original swallowed the exception message — kept it for debugging
            return $this->errorResponse('Failed to delete garment', 500, $e->getMessage());
        }
    }
}
