<?php

namespace App\Services;

use App\Models\ClothCategory;

class ClothCategoryService
{
    public function getAll($request)
    {
        $query = ClothCategory::query();



        if ($request->filled('active')) {
            $query->where('is_active', $request->active);
        }

        return $query->latest()->get();
    }

    public function store(array $data): ClothCategory
    {
        return ClothCategory::create($data);
    }

    public function update(ClothCategory $category, array $data): ClothCategory
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(ClothCategory $category): bool
    {
        return $category->delete();
    }
}