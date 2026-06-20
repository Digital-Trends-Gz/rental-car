<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Notifications\OperationalNotificationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;

class NotificationsController extends Controller
{
    public function __construct(private readonly OperationalNotificationsService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        $locale = $this->notifications->resolveLocale($request);
        $branchId = $this->notifications->resolveBranchId($request, $user);
        $limit = max(1, min(100, (int) $request->integer('limit', 20)));
        $type = trim((string) $request->query('type', ''));

        $items = $this->notifications->forUser($user, $branchId, $limit, $locale, $type);
        $unreadCount = $this->notifications->unreadForUser($user, $branchId, 500, $locale, $type)->count();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'count' => $items->count(),
            'unread_count' => $unreadCount,
            'notifications' => $items,
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        $locale = $this->notifications->resolveLocale($request);
        $branchId = $this->notifications->resolveBranchId($request, $user);
        $type = trim((string) $request->query('type', ''));
        $count = $this->notifications->unreadForUser($user, $branchId, 500, $locale, $type)->count();

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'type' => $type !== '' ? $type : 'all',
            'count' => $count,
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);
        $locale = $this->notifications->resolveLocale($request);
        $branchId = $this->notifications->resolveBranchId($request, $user);
        $type = trim((string) $request->query('type', ''));
        $readAt = now();
        $databaseUpdated = $user->unreadNotifications()->update([
            'read_at' => $readAt,
        ]);
        $operationalUpdated = $this->notifications->markCurrentAsRead($user, $branchId, $locale, $type);

        return response()->json([
            'status' => 'success',
            'message' => 'Notifications marked as read.',
            'read_count' => $databaseUpdated + $operationalUpdated,
            'database_read_count' => $databaseUpdated,
            'operational_read_count' => $operationalUpdated,
            'unread_count' => $this->notifications->unreadForUser($user, $branchId, 500, $locale, $type)->count(),
            'read_at' => $readAt->toIso8601String(),
        ]);
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        return $user;
    }
}
