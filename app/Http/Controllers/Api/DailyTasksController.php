<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tasks\DailyTasksService;
use App\Support\BranchAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyTasksController extends Controller
{
    public function __construct(
        private readonly DailyTasksService $dailyTasks,
        private readonly BranchAccess $branchAccess,
    ) {
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in(['all', 'pickup', 'return', 'maintenance', 'cleaning'])],
        ]);

        $locale = $this->resolveLocale($request);
        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today();
        $branchId = $this->resolveBranchId($request, $user);

        $payload = $this->dailyTasks->timeline(
            user: $user,
            date: $date,
            branchId: $branchId,
            type: $validated['type'] ?? null,
            locale: $locale,
        );

        unset($payload['filters']);

        return $this->jsonUtf8($payload);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $locale = $this->resolveLocale($request);

        return $this->jsonUtf8([
            'filters' => $this->taskStatusFilters($locale),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate($this->statusRules());

        $status = $this->dailyTasks->start(
            user: $user,
            taskType: $validated['task_type'],
            sourceType: $validated['source_type'],
            sourceId: (int) $validated['source_id'],
            notes: $validated['notes'] ?? null,
        );

        return $this->jsonUtf8([
            'message' => 'Task started successfully.',
            'task_status' => $status,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate($this->statusRules());

        $status = $this->dailyTasks->complete(
            user: $user,
            taskType: $validated['task_type'],
            sourceType: $validated['source_type'],
            sourceId: (int) $validated['source_id'],
            notes: $validated['notes'] ?? null,
        );

        return $this->jsonUtf8([
            'message' => 'Task completed successfully.',
            'task_status' => $status,
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            ...$this->statusRules(),
            'date' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
        ]);

        $scheduledAt = $validated['scheduled_at'] ?? null;

        if (!$scheduledAt && !empty($validated['scheduled_time'])) {
            $date = !empty($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today();
            [$hour, $minute] = explode(':', $validated['scheduled_time']);
            $scheduledAt = $date->setTime((int) $hour, (int) $minute);
        }

        if (!$scheduledAt) {
            return $this->jsonUtf8([
                'message' => 'The scheduled_at or scheduled_time field is required.',
                'errors' => [
                    'scheduled_at' => ['The scheduled_at or scheduled_time field is required.'],
                ],
            ], 422);
        }

        $status = $this->dailyTasks->schedule(
            user: $user,
            taskType: $validated['task_type'],
            sourceType: $validated['source_type'],
            sourceId: (int) $validated['source_id'],
            scheduledAt: Carbon::parse($scheduledAt),
            notes: $validated['notes'] ?? null,
        );

        return $this->jsonUtf8([
            'message' => 'Task time updated successfully.',
            'task_status' => $status,
        ]);
    }

    private function statusRules(): array
    {
        return [
            'task_type' => ['required', Rule::in(['pickup', 'return', 'maintenance', 'cleaning'])],
            'source_type' => ['required', Rule::in(['reservation', 'contract', 'maintenance', 'car'])],
            'source_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function resolveBranchId(Request $request, $user): int|array|null
    {
        $requestedBranchId = $this->branchAccess->normalizeRequestedBranchId($request->input('branch_id'));

        if ($requestedBranchId && !$this->branchAccess->canAccessBranchId($user, $requestedBranchId)) {
            abort(403);
        }

        if ($this->branchAccess->canAccessAllBranches($user)) {
            return $requestedBranchId;
        }

        return $requestedBranchId ?: $this->branchAccess->accessibleBranchIds($user);
    }

    private function taskStatusFilters(string $locale): array
    {
        return [
            ['value' => 'all', 'label' => $this->translate($locale, 'All', 'الكل')],
            ['value' => 'pickup', 'label' => $this->translate($locale, 'Pickup', 'تسليم')],
            ['value' => 'return', 'label' => $this->translate($locale, 'Return', 'استلام')],
            ['value' => 'maintenance', 'label' => $this->translate($locale, 'Maintenance', 'صيانة')],
            ['value' => 'cleaning', 'label' => $this->translate($locale, 'Cleaning', 'تنظيف')],
        ];
    }

    private function translate(string $locale, string $english, string $arabic): string
    {
        return str_starts_with(strtolower($locale), 'ar') ? $arabic : $english;
    }

    private function jsonUtf8(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(
            $payload,
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_UNESCAPED_UNICODE
        );
    }

    private function resolveLocale(Request $request): string
    {
        $supportedLocales = array_values(array_filter((array) config('app.available_locales', ['en']), static fn ($locale) => is_string($locale) && $locale !== ''));
        $fallback = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $preferred = $request->getPreferredLanguage($supportedLocales);

        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return in_array($fallback, $supportedLocales, true) ? $fallback : ($supportedLocales[0] ?? 'en');
    }
}
