<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Tasks\DailyTasksService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyTasksController extends Controller
{
    public function __construct(
        private readonly DailyTasksService $dailyTasks,
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

        return response()->json($this->dailyTasks->timeline(
            user: $user,
            date: $date,
            branchId: $validated['branch_id'] ?? null,
            type: $validated['type'] ?? null,
            locale: $locale,
        ));
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

        return response()->json([
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

        return response()->json([
            'message' => 'Task completed successfully.',
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
