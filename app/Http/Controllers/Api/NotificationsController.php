<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Notifications\OperationalNotificationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    public function __construct(private readonly OperationalNotificationsService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        $locale = $this->notifications->resolveLocale($request);
        $branchId = $this->notifications->resolveBranchId($request, $user);
        $limit = max(1, min(100, (int) $request->integer('limit', 20)));
        $type = trim((string) $request->query('type', ''));

        $items = $this->notifications->forUser($user, $branchId, $limit, $locale, $type);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'count' => $items->count(),
            'notifications' => $items,
        ]);
    }
}
