<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatlogSystem\DesignRequest;
use App\Models\Design;
use App\Services\DesignService;
use Illuminate\Http\Request;

class DesignController extends Controller
{

    protected $designService;

    public function __construct(DesignService $designService)
    {
        $this->designService = $designService;
    }

    public function index()
    {
        $data = $this->designService->getAll();
        return $this->successResponse($data, 'Designs fetched successfully');
    }

    public function show($id)
    {
        $design = Design::with('category')->find($id);

        if (!$design) {
            return $this->errorResponse('Design not found', 404);
        }

        return $this->successResponse($design, 'Design fetched successfully');
    }

    public function filter(Request $request)
    {
        $data = $this->designService->filter($request);
        return $this->successResponse($data, 'Filtered designs fetched successfully');
    }

    public function store(DesignRequest $request)
    {
        $design = $this->designService->store(
            $request->validated(),
            $request->file('image')
        );

        return $this->successResponse($design, 'Design created successfully', 201);
    }

    public function update(DesignRequest $request, $id)
    {
        $design = Design::find($id);

        if (!$design) {
            return $this->errorResponse('Design not found', 404);
        }

        $updated = $this->designService->update(
            $design,
            $request->validated(),
            $request->file('image')
        );

        return $this->successResponse($updated, 'Design updated successfully');
    }

    public function destroy($id)
    {
        $design = Design::find($id);

        if (!$design) {
            return $this->errorResponse('Design not found', 404);
        }

        $this->designService->delete($design);

        return $this->successResponse(null, 'Design deleted successfully');
    }
}
