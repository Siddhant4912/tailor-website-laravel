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

Route::get('/clear-cache', function (\Illuminate\Http\Request $request) {
    $secret = env('GIT_RESET_SECRET');
    if (!$secret || $request->query('secret') !== $secret) {
        abort(404);
    }
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    return "All Laravel caches (route, config, application cache) cleared successfully!";
});

Route::get('/run-git-reset', function (\Illuminate\Http\Request $request) {
    $secret = env('GIT_RESET_SECRET');
    if (!$secret || $request->query('secret') !== $secret) {
        abort(404);
    }
    $basePath = base_path();
    $output1 = shell_exec("cd {$basePath} && git fetch --all 2>&1");
    $output2 = shell_exec("cd {$basePath} && git reset --hard origin/main 2>&1");
    return "<pre>Fetch output:\n$output1\n\nReset output:\n$output2</pre>";
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