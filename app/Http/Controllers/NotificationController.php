<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Daftar notifikasi milik user yang login (terbaru dulu).
     * ?before={id} dipakai untuk memuat notifikasi yang lebih lama.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $before = $request->integer('before');

        // Urut berdasarkan id (naik terus seiring waktu) supaya kursor "before" konsisten.
        $query = Notification::visibleTo($user)->orderByDesc('notification_id');

        if ($before > 0) {
            $query->where('notification_id', '<', $before);
        }

        $rows = $query->limit(self::PER_PAGE + 1)->get();

        return response()->json([
            'items' => $rows->take(self::PER_PAGE)->map(fn (Notification $n) => $this->present($n))->values(),
            'has_more' => $rows->count() > self::PER_PAGE,
            'unread_count' => $this->unreadCountFor($user),
        ]);
    }

    /**
     * Dipanggil berkala oleh polling di header.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'unread_count' => $this->unreadCountFor($user),
            'latest_id' => (int) Notification::visibleTo($user)->max('notification_id'),
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai dibaca, lalu beri tahu halaman tujuannya.
     * Tujuan dihitung di server dari role user, bukan dari data yang dikirim browser.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        // 404 jika bukan milik user, bukan jenis role-nya, atau bukan cabangnya.
        $notification = Notification::visibleTo($user)
            ->where('notification_id', $id)
            ->firstOrFail();

        $url = $notification->destinationUrl($user->role_key);

        abort_if($url === null, 422, 'Halaman tujuan notifikasi tidak tersedia.');

        if (! $notification->is_read) {
            $notification->forceFill([
                'is_read' => true,
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'redirect_url' => $url,
            'unread_count' => $this->unreadCountFor($user),
        ]);
    }

    /**
     * Tandai semua notifikasi MILIK user yang login sebagai dibaca.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        Notification::visibleTo($user)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'unread_count' => $this->unreadCountFor($user),
        ]);
    }

    private function authenticatedUser(Request $request): Pengguna
    {
        $user = $request->user();
        abort_unless($user instanceof Pengguna, 401);

        return $user;
    }

    private function unreadCountFor(Pengguna $user): int
    {
        return Notification::visibleTo($user)->unread()->count();
    }

    private function present(Notification $n): array
    {
        return [
            'id' => $n->notification_id,
            'type' => $n->type,
            'title' => $n->title,
            'description' => $n->description,
            'is_read' => (bool) $n->is_read,
            'created_at' => $n->created_at?->toIso8601String(),
            'read_at' => $n->read_at?->toIso8601String(),
        ];
    }
}
