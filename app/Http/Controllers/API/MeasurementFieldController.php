<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MeasurementFieldController extends Controller
{
    public function index()
    {
        $fields = \App\Models\MeasurementField::all();
        return response()->json(['success' => true, 'data' => $fields]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:measurement_fields,name',
            'display_name' => 'required|string',
            'unit' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $field = \App\Models\MeasurementField::create($data);
        return response()->json(['success' => true, 'data' => $field], 201);
    }

    public function show(string $id)
    {
        $field = \App\Models\MeasurementField::findOrFail($id);
        return response()->json(['success' => true, 'data' => $field]);
    }

    public function update(Request $request, string $id)
    {
        $field = \App\Models\MeasurementField::findOrFail($id);

        $data = $request->validate([
            'name' => 'string|unique:measurement_fields,name,' . $id,
            'display_name' => 'string',
            'unit' => 'string',
            'is_active' => 'boolean',
        ]);

        $field->update($data);
        return response()->json(['success' => true, 'data' => $field]);
    }

    public function destroy(string $id)
    {
        $field = \App\Models\MeasurementField::findOrFail($id);
        $field->delete();
        return response()->json(['success' => true, 'message' => 'Deleted successfully']);
    }
}
