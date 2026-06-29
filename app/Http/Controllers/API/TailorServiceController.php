<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TailorServicesService;
use App\Http\Requests\Admin\Tailor\UpdateTailorServicesRequest;
use App\Http\Requests\Admin\Tailor\BulkUpdateTailorServiceRequest;
use App\Models\TailorService;

class TailorServiceController extends Controller
{

    protected $service;

    public function __construct(TailorServicesService $service)
    {
        $this->service = $service;
    }

    // GET /tailor/services
    public function index(Request $request)
    {
        try {
            $services = $this->service->getServices($request->user()->id);
            return $this->successResponse($services, 'Services fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch services', 500, $e->getMessage());
        }
    }

    // GET /tailor/services/{id}
    public function fetchone($id)
    {
        try {
            $service = TailorService::find($id);

            if (!$service) {
                // FIX: was returning 402 (Payment Required) — should be 404 (Not Found)
                return $this->errorResponse('Service not found', 404);
            }

            return $this->successResponse($service, 'Service fetched successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to fetch service', 500, $e->getMessage());
        }
    }

    // POST /tailor/services
    public function store(UpdateTailorServicesRequest $request)
    {
        try {
            $service = $this->service->createService(
                $request->user()->id,
                $request->validated()
            );
            return $this->successResponse($service, 'Service created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create service', 500, $e->getMessage());
        }
    }

    // PUT /tailor/services/{id}
    public function update(UpdateTailorServicesRequest $request, $id)
    {
        try {
            $service = $this->service->updateService(
                $request->user()->id,
                $id,
                $request->validated()
            );
            return $this->successResponse($service, 'Service updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update service', 500, $e->getMessage());
        }
    }

    // PUT /tailor/services/bulk
    public function bulkUpdate(BulkUpdateTailorServiceRequest $request)
    {
        try {
            $updatedServices = $this->service->bulkUpdate(
                $request->user()->id,
                $request->validated()['services']
            );
            return $this->successResponse($updatedServices, 'Services updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update services', 500, $e->getMessage());
        }
    }

    // DELETE /tailor/services/{id}
    public function destroy(Request $request, $id)
    {
        try {
            $this->service->deleteService($request->user()->id, $id);
            return $this->successResponse(null, 'Service deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete service', 500, $e->getMessage());
        }
    }
}
