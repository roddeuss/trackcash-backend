<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Bisa pakai ?status=unread|all
     */
    public function index(Request $request)
    {
        try {
            $query = Notification::forUser(Auth::id())->active()->orderByDesc('id');

            if ($request->query('status') === 'unread') {
                $query->unread();
            }

            $notifications = $query->get();

            return response()->json([
                'success' => true,
                'data'    => $notifications,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching notifications', ['err' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount()
    {
        try {
            $count = Notification::forUser(Auth::id())->unread()->count();

            return response()->json([
                'success' => true,
                'count'   => $count,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching unread count', ['err' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count',
            ], 500);
        }
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markRead($id)
    {
        try {
            $notif = Notification::forUser(Auth::id())->active()->findOrFail($id);

            if (is_null($notif->read_at)) {
                $notif->update([
                    'read_at'   => Carbon::now(),
                    'updated_by'=> Auth::id(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data'    => $notif,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking notification read', ['err' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllRead()
    {
        try {
            Notification::forUser(Auth::id())
                ->unread()
                ->update([
                    'read_at'   => Carbon::now(),
                    'updated_by'=> Auth::id(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error marking all notifications read', ['err' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications read',
            ], 500);
        }
    }

    /**
     * DELETE /api/notifications/{id}
     * Soft delete
     */
    public function destroy($id)
    {
        try {
            $notif = Notification::forUser(Auth::id())->findOrFail($id);

            $notif->update([
                'deleted'    => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting notification', ['err' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
            ], 500);
        }
    }
}
