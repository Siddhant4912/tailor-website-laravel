<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get list of notifications for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $notifications = $request->user()->notifications()->take(50)->get();
            return $this->successResponse($notifications, 'Notifications fetched successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch notifications', 500, $e->getMessage());
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $notification = $request->user()->notifications()->where('id', $id)->first();
            
            if (!$notification) {
                return $this->errorResponse('Notification not found', 404);
            }
            
            $notification->markAsRead();
            return $this->successResponse(null, 'Notification marked as read');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark notification as read', 500, $e->getMessage());
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $request->user()->unreadNotifications->markAsRead();
            return $this->successResponse(null, 'All notifications marked as read');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark all notifications as read', 500, $e->getMessage());
        }
    }
}
