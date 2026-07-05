<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['role:admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        return "Admin Dashboard";
    });
});

Route::middleware(['role:tailor'])->group(function () {
    Route::get('/tailor/dashboard', function () {
        return "Tailor Dashboard";
    });
});


// Fallback image delivery route for cPanel/shared hosting (no symlink required)
Route::get('/storage/{path}', function ($path) {
    // Prevent directory traversal
    $path = str_replace('..', '', $path);
    
    $fullPath = storage_path('app/public/' . $path);
    
    if (!\Illuminate\Support\Facades\File::exists($fullPath)) {
        abort(404);
    }
    
    $file = \Illuminate\Support\Facades\File::get($fullPath);
    $type = \Illuminate\Support\Facades\File::mimeType($fullPath);
    
    return response($file, 200)->header("Content-Type", $type);
})->where('path', '.*');