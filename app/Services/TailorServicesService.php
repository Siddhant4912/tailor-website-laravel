<?php

namespace App\Services;

use App\Models\TailorService;
use App\Models\TailorProfile;
use Illuminate\Support\Facades\DB;

class TailorServicesService
{
    private function getTailorProfile($userId)
    {
        return TailorProfile::where('user_id', $userId)->firstOrFail();
    }

    public function getServices(int $userId)
    {
        $tailorProfile = $this->getTailorProfile($userId);
        return TailorService::where('tailor_id', $tailorProfile->id)->get();
    }

    public function createService(int $userId, array $data): TailorService
    {
        $tailorProfile = $this->getTailorProfile($userId);
        $data['tailor_id'] = $tailorProfile->id;

        return TailorService::create($data);
    }

    public function updateService(int $userId, int $id, array $data): TailorService
    {
        $tailorProfile = $this->getTailorProfile($userId);

        $service = TailorService::where('id', $id)
                    ->where('tailor_id', $tailorProfile->id)
                    ->firstOrFail();

        $service->update($data);

        return $service;
    }

    public function bulkUpdate(int $userId, array $services)
{
    $tailorProfile = $this->getTailorProfile($userId);
    $updatedServices = [];
    DB::beginTransaction();

    try {
        foreach ($services as $serviceData) {

            // Use first() instead of firstOrFail() to prevent 500
            $service = TailorService::where('id', $serviceData['id'])
                        ->where('tailor_id', $tailorProfile->id)
                        ->first();

            if (!$service) continue; // skip invalid services

            $service->update([
                'service_name' => $serviceData['service_name'],
                'base_price' => $serviceData['base_price']
            ]);

            $updatedServices[] = $service;
        }

        DB::commit();

        // Return all successfully updated services
        return $updatedServices;

    } catch (\Exception $e) {
        DB::rollBack();
        // log exact error for debugging
        \Log::error('Bulk update failed: '.$e->getMessage());
        throw $e; // will return 500 with proper message in development
    }
}

    public function deleteService(int $userId, int $id): bool
    {
        $tailorProfile = $this->getTailorProfile($userId);

        $service = TailorService::where('id', $id)
                    ->where('tailor_id', $tailorProfile->id)
                    ->firstOrFail();

        return $service->delete();
    }
}