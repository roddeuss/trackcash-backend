<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationService
{
    /**
     * Buat notifikasi standar.
     *
     * @param int         $userId
     * @param string      $type        ex: transaction_created, transaction_updated, transaction_deleted
     * @param string      $title
     * @param string|null $message
     * @param array|null  $data        payload tambahan (transaction_id, amount, category_id, dsb)
     * @param string      $severity    info|success|warning|error
     * @param string|null $actionUrl   link ke halaman detail
     * @return \App\Models\Notification
     */
    public static function create(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        string $severity = 'info',
        ?string $actionUrl = null
    ): Notification {
        return Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'severity'   => $severity,
            'title'      => $title,
            'message'    => $message,
            'data'       => $data,
            'action_url' => $actionUrl,
            'created_by' => Auth::id() ?: $userId,
        ]);
    }
}
