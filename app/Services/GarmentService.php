<?php

namespace App\Services;

use App\Models\Garment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GarmentService
{
    // ================= HELPER =================
    // XAMPP var symlink kaam karat nahi
    // mhanun public/storage/garments madhe pan copy kartो
    private function copyToPublic(string $relativePath): void
    {
        $source      = storage_path('app/public/' . $relativePath);
        $destination = public_path('storage/' . $relativePath);

        // Destination folder naste tar create kar
        $destDir = dirname($destination);
        if (!file_exists($destDir)) {
            mkdir($destDir, 0777, true);
        }

        if (file_exists($source)) {
            copy($source, $destination);
        }
    }

    private function deleteFromPublic(string $relativePath): void
    {
        $destination = public_path('storage/' . $relativePath);
        if (file_exists($destination)) {
            unlink($destination);
        }
    }

    // ================= CREATE =================
    public function create(array $data): Garment
    {
        return DB::transaction(function () use ($data) {
            $imagesPaths = [];

            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store('garments', 'public');
                        $this->copyToPublic($path); // XAMPP fix
                        $imagesPaths[] = $path;
                    }
                }
            }

            $garment = Garment::create([
                'category_id'         => $data['category_id'],
                'design_id'           => $data['design_id'],
                'name'                => $data['name'],
                'description'         => $data['description'] ?? null,
                'price'               => $data['price'],
                'secondary_price'     => $data['secondary_price'] ?? null,
                'stitching_time_days' => $data['stitching_time'] ?? 3,
                'images'              => json_encode($imagesPaths),
                'is_active'           => 1,
            ]);

            if (!empty($data['measurements'])) {
                foreach ($data['measurements'] as $m) {
                    $garment->measurements()->create([
                        'field_name'  => $m['field_name'],
                        'unit'        => $m['unit'] ?? 'inch',
                        'is_required' => $m['is_required'] ?? true,
                    ]);
                }
            }

            return $garment->load(['measurements', 'category', 'design']);
        });
    }

    // ================= UPDATE =================
    public function update(Garment $garment, array $data): Garment
    {
        return DB::transaction(function () use ($garment, $data) {
            $oldPaths = $garment->rawImages();

            // Extract relative paths from incoming existing image URLs
            $existingPaths = [];
            if (isset($data['existing_images']) && is_array($data['existing_images'])) {
                foreach ($data['existing_images'] as $url) {
                    $path = $url;
                    if (str_contains($url, '/storage/')) {
                        $path = explode('/storage/', $url)[1];
                    }
                    $existingPaths[] = $path;
                }
            }

            // Delete physically any old image paths that are no longer kept
            foreach ($oldPaths as $imgPath) {
                if (!in_array($imgPath, $existingPaths)) {
                    Storage::disk('public')->delete($imgPath);
                    $this->deleteFromPublic($imgPath); // XAMPP fix
                }
            }

            // Start list with kept existing paths
            $imagesPaths = $existingPaths;

            // Upload and append new images if provided
            if (isset($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store('garments', 'public');
                        $this->copyToPublic($path); // XAMPP fix
                        $imagesPaths[] = $path;
                    }
                }
            }

            $garment->update([
                'category_id'         => $data['category_id']    ?? $garment->category_id,
                'design_id'           => $data['design_id']      ?? $garment->design_id,
                'name'                => $data['name']           ?? $garment->name,
                'description'         => $data['description']    ?? $garment->description,
                'price'               => $data['price']          ?? $garment->price,
                'secondary_price'     => array_key_exists('secondary_price', $data) ? $data['secondary_price'] : $garment->secondary_price,
                'stitching_time_days' => $data['stitching_time'] ?? $garment->stitching_time_days,
                'images'              => json_encode($imagesPaths),
            ]);

            if (isset($data['measurements'])) {
                $garment->measurements()->delete();
                foreach ($data['measurements'] as $m) {
                    $garment->measurements()->create([
                        'field_name'  => $m['field_name'],
                        'unit'        => $m['unit'] ?? 'inch',
                        'is_required' => $m['is_required'] ?? true,
                    ]);
                }
            }

            return $garment->load('measurements', 'category', 'design');
        });
    }

    // ================= LIST / FIND =================
    public function list()
    {
        return Garment::with(['category', 'design', 'measurements'])->latest()->get();
    }

    public function find($id): Garment
    {
        return Garment::with(['category', 'design', 'measurements'])->findOrFail($id);
    }

    // ================= DELETE =================
    public function delete(Garment $garment): bool
    {
        return DB::transaction(function () use ($garment) {
            foreach ($garment->rawImages() as $img) {
                if (Storage::disk('public')->exists($img)) {
                    Storage::disk('public')->delete($img);
                }
                $this->deleteFromPublic($img); // XAMPP fix
            }

            $garment->measurements()->delete();

            return $garment->delete();
        });
    }

    // ================= DELETE SINGLE IMAGE =================
    public function deleteImage(string $imageToDelete, int $id): array
    {
        $garment = Garment::findOrFail($id);

        $updated = array_values(
            array_filter(
                $garment->rawImages(),
                fn($img) => $img !== $imageToDelete
            )
        );

        Storage::disk('public')->delete($imageToDelete);
        $this->deleteFromPublic($imageToDelete); // XAMPP fix

        $garment->update(['images' => json_encode($updated)]);

        return ['success' => true, 'message' => 'Image deleted'];
    }
}