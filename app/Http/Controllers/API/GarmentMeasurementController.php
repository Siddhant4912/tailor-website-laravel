<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Garments\GarmentMeasurementRequest;
use App\Models\GarmentMeasurement;

class GarmentMeasurementController extends Controller
{

    // FIX: index() was completely missing — you had no way to list measurements for a garment
    // GET /garments/{garmentId}/measurements
    public function index($garmentId)
    {
        try {
            $measurements = GarmentMeasurement::where('garment_id', $garmentId)->get();
            return $this->successResponse($measurements, 'Measurements fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch measurements', 500, $e->getMessage());
        }
    }

    // POST /garments/{garmentId}/measurements
    public function store(GarmentMeasurementRequest $request)
    {
        try {
            $data = GarmentMeasurement::create($request->validated());
            return $this->successResponse($data, 'Measurement created', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create measurement', 500, $e->getMessage());
        }
    }

    // PUT /measurements/{measurement}
    public function update(GarmentMeasurementRequest $request, GarmentMeasurement $measurement)
    {
        try {
            $measurement->update($request->validated());
            return $this->successResponse($measurement, 'Measurement updated');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update measurement', 500, $e->getMessage());
        }
    }

    // DELETE /measurements/{measurement}
    public function destroy(GarmentMeasurement $measurement)
    {
        try {
            $measurement->delete();
            return $this->successResponse(null, 'Measurement deleted');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete measurement', 500, $e->getMessage());
        }
    }
}
