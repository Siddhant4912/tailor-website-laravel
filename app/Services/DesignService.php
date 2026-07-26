<?php

namespace App\Services;

use App\Models\Design;
use Illuminate\Support\Facades\Storage;

class DesignService
{
    // ✅ GET ALL
    public function getAll()
    {
        return Design::with(['category', 'garments'])->latest()->get();
    }

    // ✅ FILTER
    public function filter($request)
    {
        $query = Design::with(['category', 'garments']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 🔥 Gender filter (IMPORTANT)
        if ($request->filled('gender')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('gender', $request->gender);
            });
        }

        return $query->latest()->get();
    }

    // ✅ STORE
    public function store(array $data, $file = null)
    {
        $path = null;

        if ($file) {
            $path = $file->store('designs', 'public');
        }

        return Design::create([
            'name' => $data['name'],
            'image' => $path,
            'category_id' => $data['category_id'],
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'additional_price' => $data['additional_price'] ?? 0.00,
            'secondary_price' => $data['secondary_price'] ?? 0.00,
            'is_active' => array_key_exists('is_active', $data) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
        ]);
    }

    // ✅ UPDATE
    public function update(Design $design, array $data, $file = null)
    {
        // image update
        if ($file) {
            // 🔥 old image delete
            if ($design->image && Storage::disk('public')->exists($design->image)) {
                Storage::disk('public')->delete($design->image);
            }

            $data['image'] = $file->store('designs', 'public');
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $design->update($data);

        return $design->fresh();
    }

    // ✅ DELETE
    public function delete(Design $design)
    {
        return $design->delete();
    }
}