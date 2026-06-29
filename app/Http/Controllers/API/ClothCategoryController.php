<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ClothCategory;
use App\Services\ClothCategoryService;
use App\Http\Requests\CatlogSystem\ClothCategoryRequest;

class ClothCategoryController extends Controller
{

    protected $categoryService;

    public function __construct(ClothCategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request)
    {
        $data = $this->categoryService->getAll($request);
        return $this->successResponse($data, 'Categories fetched successfully');
    }

    public function show($id)
    {
        $category = ClothCategory::with('designs')->find($id); // FIX: eager-load designs

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        return $this->successResponse($category, 'Category fetched successfully');
    }

    public function store(ClothCategoryRequest $request)
    {
        $category = $this->categoryService->store($request->validated());
        return $this->successResponse($category, 'Category created successfully', 201);
    }

    public function update(ClothCategoryRequest $request, $id)
    {
        $category = ClothCategory::find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $category = $this->categoryService->update($category, $request->validated());

        return $this->successResponse($category, 'Category updated successfully');
    }

    public function destroy($id)
    {
        $category = ClothCategory::find($id);

        if (!$category) {
            return $this->errorResponse('Category not found', 404);
        }

        $this->categoryService->delete($category);

        return $this->successResponse(null, 'Category deleted successfully');
    }
}
