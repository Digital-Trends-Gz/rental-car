<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Clients\ClientStatusService;
use App\Support\BranchAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    public function __construct(
        private readonly BranchAccess $branchAccess,
        private readonly ClientStatusService $clientStatusService
    ) {}

    public function status(Request $request, User $client): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor, 401);

        $this->authorizeClientAccess($actor, $client);

        return response()->json(
            $this->clientStatusService->build($client, $this->resolveLocale($request))
        );
    }

    private function authorizeClientAccess(User $actor, User $client): void
    {
        abort_unless($client->role === UserRole::CLIENT, 404);

        if ($actor->role === UserRole::CLIENT) {
            abort_unless((int) $actor->id === (int) $client->id, 403);

            return;
        }

        abort_unless(in_array($actor->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true), 403);

        if ($actor->role !== UserRole::ADMIN) {
            return;
        }

        abort_unless((int) $actor->tenant_id === (int) $client->tenant_id, 403);

        if (!$this->branchAccess->canAccessAllBranches($actor)) {
            abort_unless(!empty($actor->branch_id) && (int) $actor->branch_id === (int) $client->branch_id, 403);
        }
    }

    private function resolveLocale(Request $request): string
    {
        $locale = strtolower(substr((string) $request->header('Accept-Language', 'en'), 0, 2));

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }
}
