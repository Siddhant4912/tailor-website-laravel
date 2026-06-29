<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GstSetting;
use Illuminate\Http\Request;

class GstController extends Controller
{

    // GET /gst
    public function index()
    {
        try {
            return $this->successResponse(
                GstSetting::latest()->get(),
                'GST settings fetched'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch GST settings', 500, $e->getMessage());
        }
    }

    // GET /gst/active  — used by OrderService when calculating order total
    // FIX: was missing entirely — OrderService needs to know the current active rate
    public function active()
    {
        try {
            $gst = GstSetting::where('is_active', true)
                ->latest()
                ->first();

            if (!$gst) {
                return $this->errorResponse('No active GST setting found', 404);
            }

            return $this->successResponse($gst, 'Active GST fetched');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed', 500, $e->getMessage());
        }
    }

    // POST /gst
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'rate'         => 'required|numeric|min:0',
                'is_active'    => 'required|boolean',
                'is_inclusive' => 'nullable|boolean',
            ]);

            // FIX: if this record is active, deactivate all others first
            // so only one active GST rate exists at a time
            if ($validated['is_active']) {
                GstSetting::where('is_active', true)->update(['is_active' => false]);
            }

            $gst = GstSetting::create($validated);

            // Clear active GST cache
            \Illuminate\Support\Facades\Cache::forget('active_gst_rate');
            \Illuminate\Support\Facades\Cache::forget('active_gst_inclusive');

            return $this->successResponse($gst, 'GST created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create GST', 500, $e->getMessage());
        }
    }

    // PUT /gst/{id}
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rate'         => 'required|numeric|min:0',
                'is_active'    => 'required|boolean',
                'is_inclusive' => 'nullable|boolean',
            ]);

            $gst = GstSetting::findOrFail($id);

            // FIX: same — deactivate others when activating this one
            if ($validated['is_active']) {
                GstSetting::where('is_active', true)
                    ->where('id', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $gst->update($validated);

            // Clear active GST cache
            \Illuminate\Support\Facades\Cache::forget('active_gst_rate');
            \Illuminate\Support\Facades\Cache::forget('active_gst_inclusive');

            return $this->successResponse($gst, 'GST updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update GST', 500, $e->getMessage());
        }
    }

    // DELETE /gst/{id}
    // FIX: delete was completely missing
    public function destroy($id)
    {
        try {
            $gst = GstSetting::findOrFail($id);

            if ($gst->is_active) {
                return $this->errorResponse('Cannot delete the active GST setting. Deactivate it first.', 422);
            }

            $gst->delete();
            \Illuminate\Support\Facades\Cache::forget('active_gst_rate');
            \Illuminate\Support\Facades\Cache::forget('active_gst_inclusive');
            return $this->successResponse(null, 'GST deleted');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete GST', 500, $e->getMessage());
        }
    }
}
