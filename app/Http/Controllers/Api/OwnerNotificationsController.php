<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\Notifications\OwnerNotificationsService;
use App\Support\BranchAccess;
use App\Support\TenantTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerNotificationsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly OwnerNotificationsService $notifications,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user, $locale);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $latest = $this->notifications->paginated($user, $branchId, $locale, $perPage, $page);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'tenant_id' => (int) $user->tenant_id,
            'branch_id' => $branchId,
            'section_titles' => [
                'active_alerts' => $this->ownerText('notifications.sections.active_alerts', $locale, 'Active alerts'),
                'latest_notifications' => $this->ownerText('notifications.sections.latest_notifications', $locale, 'Latest notifications'),
            ],
            'active_alerts' => $this->notifications->activeAlerts($user, $branchId, $locale),
            'latest_notifications' => $latest->items(),
            'pagination' => [
                'current_page' => $latest->currentPage(),
                'per_page' => $latest->perPage(),
                'total' => $latest->total(),
                'last_page' => $latest->lastPage(),
                'has_more' => $latest->hasMorePages(),
            ],
            'unread_count' => $this->notifications->unreadCount($user, $branchId, $locale),
        ]);
    }

    public function count(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user, $locale);

        return response()->json([
            'status' => 'success',
            'locale' => $locale,
            'branch_id' => $branchId,
            'unread_count' => $this->notifications->unreadCount($user, $branchId, $locale),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->authorizedOwner($request);
        $locale = $this->resolveLocale($request);
        $branchId = $this->resolveOwnerBranchId($request, $user, $locale);
        $readCount = $this->notifications->markLatestAsRead($user, $branchId, $locale);

        return response()->json([
            'status' => 'success',
            'message' => $this->ownerText('notifications.messages.marked_read', $locale, 'Notifications marked as read.'),
            'branch_id' => $branchId,
            'read_count' => $readCount,
            'unread_count' => $this->notifications->unreadCount($user, $branchId, $locale),
        ]);
    }

    private function authorizedOwner(Request $request): User
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->role === UserRole::ADMIN, 403);
        abort_unless(!empty($user->tenant_id), 403);
        abort_unless($this->branchAccess->canUseOwnerApis($user), 403);

        return $user;
    }

    private function resolveOwnerBranchId(Request $request, User $user, string $locale): ?int
    {
        $branchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        $resolvedBranchId = $this->branchAccess->resolveAccessibleBranchId($user, $branchId);

        if (!$this->branchAccess->canAccessAllBranches($user)) {
            return $resolvedBranchId;
        }

        if (!$resolvedBranchId) {
            return null;
        }

        $exists = Branch::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->whereKey($resolvedBranchId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'branch_id' => [$this->ownerText('errors.branch_invalid', $locale, 'Selected branch is invalid or not accessible.')],
            ]);
        }

        return $resolvedBranchId;
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter(
            (array) config('app.available_locales', ['en']),
            static fn ($locale) => is_string($locale) && $locale !== ''
        ));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }

    private function ownerText(string $key, string $locale, string $fallback): string
    {
        $translationKey = 'owner_api.'.$key;
        $fileKey = 'site.'.$translationKey;
        $fileFallback = trans($fileKey, [], $locale);

        if (!is_string($fileFallback) || $fileFallback === $fileKey) {
            $fileFallback = $fallback;
        }

        return TenantTranslations::get($translationKey, $locale, $fileFallback);
    }
}
