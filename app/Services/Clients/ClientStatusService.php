<?php

namespace App\Services\Clients;

use App\Enums\ContractStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\ClientDocument;
use App\Models\ClientFlag;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\ClientReturnDebt;
use Carbon\CarbonImmutable;
use Throwable;

class ClientStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $client, string $locale = 'en'): array
    {
        $client = $this->hydrateClient($client);
        $locale = $this->normalizeLocale($locale);
        $flags = array_merge(
            $this->manualFlags($client, $locale),
            $this->automaticFlags($client, $locale)
        );

        $overallStatus = $this->overallStatus($flags);
        $blockingTypes = collect($flags)
            ->filter(fn (array $flag): bool => (bool) ($flag['blocks_booking'] ?? false))
            ->values()
            ->all();

        return [
            'client_id' => $client->id,
            'client' => [
                'id' => $client->id,
                'name' => (string) $client->name,
                'email' => (string) $client->email,
                'is_active' => (bool) $client->is_active,
                'branch_id' => $client->branch_id,
            ],
            'overall_status' => $overallStatus,
            'overall_label' => $this->label("overall.{$overallStatus}", $locale),
            'can_book' => $blockingTypes === [],
            'blocking_flags' => array_map(fn (array $flag): string => (string) $flag['type'], $blockingTypes),
            'flags_count' => count($flags),
            'flags' => array_values($flags),
        ];
    }

    private function hydrateClient(User $client): User
    {
        $attributes = $client->getAttributes();
        foreach (['tenant_id', 'branch_id', 'role', 'is_active'] as $attribute) {
            if (!array_key_exists($attribute, $attributes)) {
                return User::withoutGlobalScopes()->find($client->id) ?? $client;
            }
        }

        return $client;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualFlags(User $client, string $locale): array
    {
        return ClientFlag::query()
            ->where('user_id', $client->id)
            ->where('is_active', true)
            ->orderByRaw("FIELD(severity, 'danger', 'warning', 'info')")
            ->latest('id')
            ->get()
            ->map(function (ClientFlag $flag) use ($locale): array {
                $type = (string) $flag->type;

                return [
                    'id' => $flag->id,
                    'type' => $type,
                    'severity' => $this->normalizeSeverity($flag->severity),
                    'label' => $flag->title ?: $this->label("type.{$type}", $locale),
                    'description' => $flag->description ?: $this->label("description.{$type}", $locale),
                    'source' => 'manual',
                    'source_type' => $flag->source_type,
                    'source_id' => $flag->source_id,
                    'blocks_booking' => $type === 'blocked',
                    'created_at' => optional($flag->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function automaticFlags(User $client, string $locale): array
    {
        $flags = [];

        if (! (bool) $client->is_active) {
            $flags[] = [
                'type' => 'blocked',
                'severity' => 'danger',
                'label' => $this->label('type.blocked', $locale),
                'description' => $this->label('description.blocked', $locale),
                'source' => 'automatic',
                'blocks_booking' => true,
            ];
        }

        $debt = $this->clientDebt($client);
        if ($debt['total'] > 0) {
            $flags[] = [
                'type' => 'debt',
                'severity' => 'danger',
                'label' => $this->label('type.debt', $locale),
                'description' => $this->label('description.debt', $locale, [
                    'amount' => number_format($debt['total'], 2),
                ]),
                'source' => 'automatic',
                'meta' => $debt,
                'blocks_booking' => true,
            ];
        }

        foreach ($this->expiredDocumentFlags($client, $locale) as $flag) {
            $flags[] = $flag;
        }

        $lateReturn = $this->latestLateReturn($client);
        if ($lateReturn !== null) {
            $flags[] = [
                'type' => 'late_return',
                'severity' => 'warning',
                'label' => $this->label('type.late_return', $locale),
                'description' => $this->label('description.late_return', $locale, [
                    'contract' => (string) $lateReturn->contract_number,
                    'date' => optional($lateReturn->actual_return_time)->format('Y-m-d H:i') ?? '-',
                ]),
                'source' => 'automatic',
                'source_type' => Contract::class,
                'source_id' => $lateReturn->id,
                'blocks_booking' => false,
            ];
        }

        if ($this->isNewCustomer($client)) {
            $flags[] = [
                'type' => 'new_customer',
                'severity' => 'info',
                'label' => $this->label('type.new_customer', $locale),
                'description' => $this->label('description.new_customer', $locale),
                'source' => 'automatic',
                'blocks_booking' => false,
            ];
        }

        return $flags;
    }

    /**
     * @return array{reservation_balance: float, return_charges: float, total: float}
     */
    private function clientDebt(User $client): array
    {
        $reservationIds = Reservation::query()
            ->where('user_id', $client->id)
            ->whereNotIn('status', [
                ReservationStatus::CANCELLED->value,
                ReservationStatus::NO_SHOW->value,
            ])
            ->pluck('id');

        $reservationTotal = (float) Reservation::query()
            ->whereIn('id', $reservationIds)
            ->sum('total_amount');

        $paidTotal = (float) Payment::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->selectRaw('COALESCE(SUM(amount - COALESCE(refunded_amount, 0)), 0) as total')
            ->value('total');

        $reservationBalance = round(max(0, $reservationTotal - $paidTotal), 2);
        $returnCharges = round(ClientReturnDebt::outstandingTotal((int) $client->tenant_id, (int) $client->id), 2);

        return [
            'reservation_balance' => $reservationBalance,
            'return_charges' => $returnCharges,
            'total' => round($reservationBalance + $returnCharges, 2),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expiredDocumentFlags(User $client, string $locale): array
    {
        $today = CarbonImmutable::today();

        return $client->clientDocuments()
            ->get()
            ->map(function (ClientDocument $document) use ($today, $locale): ?array {
                $expiryDate = $this->documentExpiryDate($document);
                if ($expiryDate === null || $expiryDate->greaterThanOrEqualTo($today)) {
                    return null;
                }

                $type = $this->expiredDocumentType($document);

                return [
                    'type' => $type,
                    'severity' => 'warning',
                    'label' => $this->label("type.{$type}", $locale),
                    'description' => $this->label("description.{$type}", $locale, [
                        'date' => $expiryDate->toDateString(),
                    ]),
                    'source' => 'automatic',
                    'source_type' => ClientDocument::class,
                    'source_id' => $document->id,
                    'meta' => [
                        'document_type' => $document->document_type?->value ?? (string) $document->document_type,
                        'expiry_date' => $expiryDate->toDateString(),
                    ],
                    'blocks_booking' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function documentExpiryDate(ClientDocument $document): ?CarbonImmutable
    {
        $data = is_array($document->approved_data) ? $document->approved_data : [];

        foreach ([
            'expiry_date',
            'license_expiry_date',
            'identity_expiry_date',
            'passport_expiry_date',
            'residency_expiry_date',
            'visa_expiry_date',
        ] as $key) {
            $value = $data[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return CarbonImmutable::parse($value)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function expiredDocumentType(ClientDocument $document): string
    {
        $documentType = (string) ($document->document_type?->value ?? $document->document_type);
        $approvedType = strtolower((string) data_get($document->approved_data, 'document_type', ''));
        $combinedType = $documentType.' '.$approvedType;

        if (str_contains($combinedType, 'license')) {
            return 'expired_license';
        }

        if (str_contains($combinedType, 'passport')) {
            return 'expired_passport';
        }

        if (str_contains($combinedType, 'residency') || str_contains($combinedType, 'residence')) {
            return 'expired_residency';
        }

        return 'expired_document';
    }

    private function latestLateReturn(User $client): ?Contract
    {
        return Contract::query()
            ->whereHas('reservation', fn ($query) => $query->where('user_id', $client->id))
            ->whereNotNull('actual_return_time')
            ->whereNotNull('end_date')
            ->latest('actual_return_time')
            ->get()
            ->first(function (Contract $contract): bool {
                $endDate = $contract->end_date ? CarbonImmutable::parse($contract->end_date)->endOfDay() : null;
                $actualReturn = $contract->actual_return_time ? CarbonImmutable::parse($contract->actual_return_time) : null;

                return $endDate !== null && $actualReturn !== null && $actualReturn->greaterThan($endDate);
            });
    }

    private function isNewCustomer(User $client): bool
    {
        return ! Contract::query()
            ->whereHas('reservation', fn ($query) => $query->where('user_id', $client->id))
            ->where('status', ContractStatus::COMPLETED->value)
            ->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $flags
     */
    private function overallStatus(array $flags): string
    {
        $severities = array_column($flags, 'severity');

        if (in_array('danger', $severities, true)) {
            return 'danger';
        }

        if (in_array('warning', $severities, true)) {
            return 'warning';
        }

        if (in_array('info', $severities, true)) {
            return 'info';
        }

        return 'good';
    }

    private function normalizeSeverity(?string $severity): string
    {
        return in_array($severity, ['info', 'warning', 'danger'], true) ? $severity : 'info';
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(substr(trim($locale), 0, 2));

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'en';
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function label(string $key, string $locale, array $replace = []): string
    {
        $labels = [
            'overall.good' => ['en' => 'Good', 'ar' => 'جيد'],
            'overall.info' => ['en' => 'Info', 'ar' => 'معلومة'],
            'overall.warning' => ['en' => 'Needs review', 'ar' => 'يحتاج مراجعة'],
            'overall.danger' => ['en' => 'Blocked', 'ar' => 'موقوف'],
            'type.blocked' => ['en' => 'Blocked', 'ar' => 'موقوف'],
            'type.needs_review' => ['en' => 'Needs review', 'ar' => 'يحتاج مراجعة'],
            'type.debt' => ['en' => 'Debtor', 'ar' => 'مديون'],
            'type.expired_license' => ['en' => 'Expired license', 'ar' => 'رخصة منتهية'],
            'type.expired_passport' => ['en' => 'Expired passport', 'ar' => 'جواز منتهي'],
            'type.expired_residency' => ['en' => 'Expired residency', 'ar' => 'إقامة منتهية'],
            'type.expired_document' => ['en' => 'Expired document', 'ar' => 'وثيقة منتهية'],
            'type.late_return' => ['en' => 'Late return history', 'ar' => 'رجوع سابق متأخر'],
            'type.new_customer' => ['en' => 'New customer', 'ar' => 'عميل جديد'],
            'description.blocked' => ['en' => 'This client is manually blocked.', 'ar' => 'هذا العميل موقوف يدويا.'],
            'description.needs_review' => ['en' => 'This client needs manual review.', 'ar' => 'هذا العميل يحتاج مراجعة يدوية.'],
            'description.debt' => ['en' => 'Outstanding amount: :amount.', 'ar' => 'يوجد مبلغ مستحق: :amount.'],
            'description.expired_license' => ['en' => 'The license expired on :date.', 'ar' => 'انتهت الرخصة بتاريخ :date.'],
            'description.expired_passport' => ['en' => 'The passport expired on :date.', 'ar' => 'انتهى الجواز بتاريخ :date.'],
            'description.expired_residency' => ['en' => 'The residency expired on :date.', 'ar' => 'انتهت الإقامة بتاريخ :date.'],
            'description.expired_document' => ['en' => 'A client document expired on :date.', 'ar' => 'توجد وثيقة منتهية بتاريخ :date.'],
            'description.late_return' => ['en' => 'Last late return: :contract at :date.', 'ar' => 'آخر رجوع متأخر: :contract بتاريخ :date.'],
            'description.new_customer' => ['en' => 'No completed contracts yet.', 'ar' => 'لا يوجد عقود مكتملة بعد.'],
        ];

        $text = $labels[$key][$locale] ?? $labels[$key]['en'] ?? $key;

        foreach ($replace as $search => $value) {
            $text = str_replace(':'.$search, $value, $text);
        }

        return $text;
    }
}
