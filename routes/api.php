<?php
// siddhant pawar : 04-07-2026

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\AppointmentInvoiceController;
use App\Http\Controllers\API\CatalogSystemController;
use App\Http\Controllers\API\ClothCategoryController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DeliveryStaffAppointmentController;
use App\Http\Controllers\API\DesignController;
use App\Http\Controllers\API\GarmentController;
use App\Http\Controllers\API\GarmentMeasurementController;
use App\Http\Controllers\API\GstController;
use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\TailorProfileController;
use App\Http\Controllers\API\TailorServiceController;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\API\DeliveryStaffController;

Route::get('/run-link', function () {
    \Illuminate\Support\Facades\Artisan::call('storage:link');
    return "Storage link created inside api folder successfully!";
});



/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/delivery-staff/login', [DeliveryStaffController::class, 'login']);

Route::get('/catalog', [CatalogSystemController::class, 'index']);
Route::get('/catalog/categories/{id}/garments', [CatalogSystemController::class, 'garmentsByCategory']);
Route::get('/catalog/designs/{id}/garments', [CatalogSystemController::class, 'garmentsByDesign']);

Route::get('/gst/active', [GstController::class, 'active']);
Route::get('/settings', [\App\Http\Controllers\API\SettingController::class, 'index']);

Route::get('/cloth-categories', [ClothCategoryController::class, 'index']);
Route::get('/designs', [DesignController::class, 'index']);
Route::get('/designs/filter', [DesignController::class, 'filter']);
Route::get('/designs/{id}', [DesignController::class, 'show']);
Route::get('/garments', [GarmentController::class, 'index']);
Route::get('/garments/{id}', [GarmentController::class, 'show']);
Route::get('/garments/{id}/measurements', [GarmentMeasurementController::class, 'index']);
Route::get('/reviews', [ReviewController::class, 'index']);

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Notification Bell Dropdowns
    Route::get('/notifications', [App\Http\Controllers\API\NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);

    Route::post('/orders/{id}/approve', [App\Http\Controllers\API\OrderController::class, 'approve']);

    Route::get('/dashboard/stats', [DashboardController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER ROUTES (FRONTEND MATCH)
    |--------------------------------------------------------------------------
    */
    Route::get('/appointments/my-appointments', [AppointmentController::class, 'myAppointments']);

    Route::post('/appointments/send-otp', [AppointmentController::class, 'sendOtp']);
    Route::post('/appointments/verify-otp', [AppointmentController::class, 'verifyOtp']);

    Route::apiResource('appointments', AppointmentController::class);

    Route::post('/appointments/{id}/start', [AppointmentController::class, 'start']);
    Route::post('/appointments/{id}/end', [AppointmentController::class, 'end']);
    Route::post('/appointments/{id}/uploads', [AppointmentController::class, 'uploadImage']);

    Route::delete('/appointments/uploads/{uploadId}', [AppointmentController::class, 'deleteUpload']);
    // Route::get('/appointments/{id}', [AppointmentController::class, 'show']);

    Route::post('/appointments/{id}/order', [OrderController::class, 'createFromAppointment']);
    Route::apiResource('orders', OrderController::class);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    Route::get('/appointment-invoices/download/{id}', [AppointmentInvoiceController::class, 'download']);
    Route::get('/invoices/{id}/download', [InvoiceController::class, 'downloadInvoice']);
    Route::get('/orders/{id}/stitching-slip', [OrderController::class, 'downloadStitchingSlip']);
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Razorpay Integration Routes
    Route::post('/payments/initiate', [InvoiceController::class, 'initiateRazorpayOrder']);
    Route::post('/payments/initiate-appointment', [AppointmentController::class, 'initiateAppointmentPayment']);
    Route::post('/payments/initiate-order', [OrderController::class, 'initiateOrderPayment']);
    Route::post('/payments/initiate-advance', [InvoiceController::class, 'initiateAdvancePayment']);
    Route::post('/appointments/pay-and-create', [AppointmentController::class, 'payAndCreate']);
    Route::post('/orders/pay-and-create', [OrderController::class, 'payAndCreate']);
    Route::post('/payments/{transaction_id}/razorpay-order', [InvoiceController::class, 'createRazorpayOrder']);
    Route::post('/payments/razorpay/verify', [InvoiceController::class, 'verifyRazorpayPayment']);

    Route::post('/payments/mock-charge', [InvoiceController::class, 'mockCharge']);

    // Admin website invoice download and generation alignments
    Route::post('/appointments/{id}/invoice', [AppointmentInvoiceController::class, 'generate']);
    Route::get('/invoices/appointment/{id}/download', [AppointmentInvoiceController::class, 'download']);
    Route::post('/orders/{id}/invoice', [InvoiceController::class, 'generate']);
    Route::get('/invoices/order/{id}/download', [InvoiceController::class, 'downloadInvoiceByOrder']);

    // =====================
    // USER PROFILE
    // =====================
    Route::get('/user/profile', [UserProfileController::class, 'profile']);
    Route::put('/user/profile', [UserProfileController::class, 'update']);
    Route::post('/user/profile/photo', [UserProfileController::class, 'updatePhoto']);
    Route::post('/user/profile/send-otp', [UserProfileController::class, 'sendOtp']);
    Route::post('/user/profile/verify-otp', [UserProfileController::class, 'verifyOtp']);

    /*
    |--------------------------------------------------------------------------
    | STAFF ROUTES
    |--------------------------------------------------------------------------
    */
    Route::prefix('staff')->middleware('role:delivery_staff')->group(function () {
        Route::get('/profile', [\App\Http\Controllers\API\StaffProfileController::class, 'getProfile']);
        Route::post('/profile/photo', [\App\Http\Controllers\API\StaffProfileController::class, 'updatePhoto']);
        Route::get('/appointments/today', [DeliveryStaffAppointmentController::class, 'today']);
        Route::get('/measurement-fields', [\App\Http\Controllers\API\MeasurementFieldController::class, 'index']);
        Route::get('/appointments/{id}', [DeliveryStaffAppointmentController::class, 'show']);
        Route::post('/appointments/{id}/start', [DeliveryStaffAppointmentController::class, 'start']);
        Route::post('/appointments/{id}/end', [DeliveryStaffAppointmentController::class, 'end']);
        Route::post('/appointments/{id}/measurements', [DeliveryStaffAppointmentController::class, 'saveMeasurements']);
        Route::post('/appointments/{id}/uploads', [DeliveryStaffAppointmentController::class, 'uploadPhoto']);
        Route::get('/appointments/{id}/catalog', [DeliveryStaffAppointmentController::class, 'catalog']);
        Route::post('/appointments/{id}/confirm-order', [DeliveryStaffAppointmentController::class, 'confirmOrder']);
        Route::post('/appointments/{id}/charge-visit-only', [DeliveryStaffAppointmentController::class, 'chargeVisitOnly']);
        Route::get('/orders', [DeliveryStaffController::class, 'myOrders']);
        Route::get('/orders/{id}', [\App\Http\Controllers\API\OrderController::class, 'show']);
        Route::post('/orders/{id}/uploads', [DeliveryStaffController::class, 'uploadOrderPhoto']);
        Route::get('/invoices/{id}/download', [\App\Http\Controllers\API\InvoiceController::class, 'downloadInvoice']);
        Route::get('/orders/{id}/stitching-slip', [\App\Http\Controllers\API\OrderController::class, 'downloadStitchingSlip']);
        Route::get('/appointment-invoices/download/{id}', [\App\Http\Controllers\API\AppointmentInvoiceController::class, 'download']);
        Route::post('/payments/collect-cash', [\App\Http\Controllers\API\CashSettlementController::class, 'collectCash']);
    });

    /*
    |--------------------------------------------------------------------------
    | TAILOR ROUTES
    |--------------------------------------------------------------------------
    */
    Route::prefix('tailor')->middleware('role:tailor')->group(function () {

        Route::get('/profile', [TailorProfileController::class, 'profile']);
        Route::put('/profile', [TailorProfileController::class, 'update']);

        Route::apiResource('services', TailorServiceController::class);
        Route::put('/services/bulk', [TailorServiceController::class, 'bulkUpdate']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ROUTES (MATCH FRONTEND ADMIN CALLS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        // =====================
        // DASHBOARD
        // =====================
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // =====================
        // SETTINGS
        // =====================
        Route::post('/settings', [\App\Http\Controllers\API\SettingController::class, 'update']);

        // =====================
        // USERS
        // =====================
        Route::get('/users', [AdminController::class, 'allUsers']);
        Route::get('/users/{id}', [AdminController::class, 'getUser']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{id}/status', [AdminController::class, 'updateStatus']);
        Route::put('/users/{id}/role', [AdminController::class, 'updateRoleStatus']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::get('/tailors', [AdminController::class, 'allTailors']);
        Route::get('/delivery-staff', [AdminController::class, 'allDeliveryStaff']);

        // =====================
        // APPOINTMENTS (MISSING ADD THIS)
        // =====================
        Route::get('/appointments', [AppointmentController::class, 'index']);

        // =====================
        // ORDERS (MISSING ADD THIS)
        // =====================
        Route::get('/orders', [OrderController::class, 'index']);
        Route::put('/orders/{id}/delivery-date', [OrderController::class, 'updateDeliveryDate']);

        // =====================
        // CATEGORIES
        // =====================
        Route::apiResource('categories', ClothCategoryController::class);

        // =====================
        // DESIGNS
        // =====================
        Route::apiResource('designs', DesignController::class);

        // =====================
        // GARMENTS
        // =====================
        Route::apiResource('garments', GarmentController::class);

        Route::get('/garments/{id}/measurements', [GarmentMeasurementController::class, 'index']);
        Route::post('/garments/measurements', [GarmentMeasurementController::class, 'store']);
        Route::put('/measurements/{measurement}', [GarmentMeasurementController::class, 'update']);
        Route::delete('/measurements/{measurement}', [GarmentMeasurementController::class, 'destroy']);

        // =====================
        // MEASUREMENT FIELDS
        // =====================
        Route::apiResource('measurement-fields', \App\Http\Controllers\API\MeasurementFieldController::class);

        // =====================
        // DELIVERY STAFF
        // =====================
        // Route::get('/delivery-staff', [DeliveryStaffController::class, 'index']);
        Route::post('/delivery-staff', [DeliveryStaffController::class, 'store']);
        Route::put('/delivery-staff/{id}', [DeliveryStaffController::class, 'update']);
        Route::delete('/delivery-staff/{id}', [DeliveryStaffController::class, 'destroy']);

        // =====================
        // GST
        // =====================
        Route::get('/gst/active', [GstController::class, 'active']);
        Route::apiResource('gst', GstController::class);

        // =====================
        // INVOICES
        // =====================
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices/generate/{orderId}', [InvoiceController::class, 'generate']);
        Route::get('/payments', [InvoiceController::class, 'transactions']);
        Route::post('/payments/{id}/mark-paid', [InvoiceController::class, 'markPaid']);
        Route::post('/payments/mock-charge', [InvoiceController::class, 'mockCharge']);

        // =====================
        // CASH SETTLEMENTS & AUDITS
        // =====================
        Route::get('/cash-settlements/pending', [\App\Http\Controllers\API\CashSettlementController::class, 'getPendingSettlements']);
        Route::post('/cash-settlements/settle', [\App\Http\Controllers\API\CashSettlementController::class, 'settle']);
        Route::get('/cash-settlements/history', [\App\Http\Controllers\API\CashSettlementController::class, 'getSettlementHistory']);
        Route::get('/payment-audit-logs', [\App\Http\Controllers\API\CashSettlementController::class, 'getAuditLogs']);
        Route::post('/payment-audit-logs/correct', [\App\Http\Controllers\API\CashSettlementController::class, 'correctPayment']);

        // tailors
        Route::put('/orders/{orderId}/items/{itemId}/assign-tailor', [OrderController::class, 'assignTailor']);
        Route::put('/orders/{orderId}/assign-delivery', [OrderController::class, 'assignDelivery']);
        // =====================
        // REVIEWS
        // =====================
        Route::get('/reviews', [ReviewController::class, 'index']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    });
});

// Temporary route for cPanel setup (Run migrations & link storage)
Route::get('/setup-cpanel', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');

        // The target folder in the backend where images are actually saved
        $target = storage_path('app/public');

        // We will try to create the symlink in two possible locations:
        // 1. The actual Document Root from the server
        // 2. The sibling 'domain' folder based on your structure (home/project/domain)
        $possibleLocations = [
            $_SERVER['DOCUMENT_ROOT'] . '/storage',
            base_path('../domain/storage')
        ];

        foreach ($possibleLocations as $link) {
            // Only try if the parent directory exists (meaning we found the right place)
            if (is_dir(dirname($link))) {
                if (is_link($link)) {
                    unlink($link);
                } elseif (is_dir($link)) {
                    \Illuminate\Support\Facades\File::deleteDirectory($link);
                }

                try {
                    symlink($target, $link);
                } catch (\Exception $e) {
                    // Ignore if it fails on one of them
                }
            }
        }

        return response()->json([
            'message' => 'Old database deleted, freshly migrated, seeded, config & cache cleared successfully!',
            'status' => 'success'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Setup failed!',
            'error' => $e->getMessage()
        ], 500);
    }
});
// Separate route just to link storage (without database migrations)
Route::get('/link-storage', function () {
    try {
        $target = storage_path('app/public');

        $possibleLocations = [
            public_path('storage'),
            $_SERVER['DOCUMENT_ROOT'] . '/storage',
        ];

        $linkedCount = 0;
        $attemptedPaths = [];

        foreach ($possibleLocations as $link) {
            $attemptedPaths[] = $link;
            // Only try if the parent directory exists
            if (is_dir(dirname($link))) {
                if (is_link($link)) {
                    unlink($link);
                } elseif (is_dir($link)) {
                    \Illuminate\Support\Facades\File::deleteDirectory($link);
                }

                try {
                    symlink($target, $link);
                    $linkedCount++;
                } catch (\Exception $e) {
                    // Ignore and try next location
                }
            }
        }

        if ($linkedCount > 0) {
            return response()->json([
                'message' => 'Storage linked successfully in ' . $linkedCount . ' location(s)!',
                'attempted_paths' => $attemptedPaths,
                'status' => 'success'
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to create storage link.',
                'attempted_paths' => $attemptedPaths,
                'status' => 'error'
            ], 500);
        }
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error linking storage!',
            'error' => $e->getMessage()
        ], 500);
    }
});
Route::get('/image-test', function () {
    $path = storage_path('app/public/garments/lk3qCUlFUegf4n4XTP8UtB34XEOay1Z4ie7mZRFc.jpg');

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path);
});
Route::get('/image/{path}', function ($path) {

    $file = storage_path('app/public/' . $path);

    if (!file_exists($file)) {
        abort(404);
    }

    return response()->file($file);

})->where('path', '.*');