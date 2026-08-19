<?php

namespace App\Http\Controllers\Client;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalExtensionRequestStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RentalExtensionRequest;
use App\Models\Reservation;
use App\Models\TenantSiteSetting;
use App\Support\CurrencyCatalog;
use App\Support\PdfRuntime;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf as SpatiePdf;
use Throwable;

class ReservationsController extends Controller
{
    public function index(Request $request)
    {

        $reservations = Reservation::where('user_id', auth()->user()->id)
            ->with(['car', 'contract', 'payments'])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $extensionRequests = RentalExtensionRequest::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('status', RentalExtensionRequestStatus::PENDING)
            ->whereHas('reservation', fn ($query) => $query->where('user_id', auth()->id()))
            ->with([
                'reservation.car:id,year,make,model,license_plate',
                'contract:id,contract_number',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RentalExtensionRequest $request) => [
                'id' => $request->id,
                'reservation_id' => $request->reservation_id,
                'reservation_number' => $request->reservation?->reservation_number,
                'contract_number' => $request->contract?->contract_number,
                'car_name' => $request->reservation?->car
                    ? trim(sprintf(
                        '%s %s %s',
                        (string) ($request->reservation->car->year ?? ''),
                        (string) ($request->reservation->car->make ?? ''),
                        (string) ($request->reservation->car->model ?? '')
                    ))
                    : null,
                'license_plate' => $request->reservation?->car?->license_plate,
                'new_end_date' => optional($request->new_end_date)?->toDateString(),
                'extra_days' => $request->extra_days,
                'extra_amount' => (float) $request->extra_amount,
                'reason' => $request->reason,
                'status' => $request->status instanceof RentalExtensionRequestStatus ? $request->status->value : (string) $request->status,
                'status_label' => $request->status instanceof RentalExtensionRequestStatus ? $request->status->label() : ucfirst((string) $request->status),
                'approve_url' => route('client.reservations.extension-requests.approve', [
                    'subdomain' => request()->route('subdomain'),
                    'reservation' => $request->reservation_id,
                    'extensionRequest' => $request->id,
                ]),
                'reject_url' => route('client.reservations.extension-requests.reject', [
                    'subdomain' => request()->route('subdomain'),
                    'reservation' => $request->reservation_id,
                    'extensionRequest' => $request->id,
                ]),
            ])
            ->values();

        return inertia('Client/Reservations/Index', [
            'reservations' => $reservations,
            'extensionRequests' => $extensionRequests,
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    public function show($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car', 'payments', 'contract']);

        return inertia('Client/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => CurrencyCatalog::forTenant($reservation->tenant),
            'hasContract' => $reservation->contract !== null,
            'contractId' => $reservation->contract?->id,
        ]);
    }

    public function print($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car.branch', 'payments', 'tenant.siteSetting.files']);
        $siteSettings = $reservation->tenant?->siteSetting ? TenantSiteSetting::forTenant($reservation->tenant) : [];
        $branding = $this->pdfBranding($reservation->tenant);

        $viewData = [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => CurrencyCatalog::forTenant($reservation->tenant)['symbol'],
            'companyLogo' => $branding['logo'],
            'companyName' => $branding['name'],
            'siteSettings' => $siteSettings,
        ];
        $fileName = $reservation->reservation_number . '.pdf';

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                return SpatiePdf::view('admin.reservations.print', $viewData)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(4, 4, 4, 4)
                    ->withBrowsershot(function (Browsershot $browsershot): void {
                        $nodeBinary = PdfRuntime::nodeBinary();
                        if ($nodeBinary) {
                            $browsershot->setNodeBinary($nodeBinary);
                        }

                        $npmBinary = PdfRuntime::npmBinary();
                        if ($npmBinary) {
                            $browsershot->setNpmBinary($npmBinary);
                        }

                        $chromePath = PdfRuntime::chromeBinary();
                        if ($chromePath) {
                            $browsershot->setChromePath($chromePath);
                        }

                        $browsershot
                            ->noSandbox()
                            ->addChromiumArguments([
                                'disable-dev-shm-usage',
                                'disable-gpu',
                            ])
                            ->setOption('printBackground', true)
                            ->setOption('preferCSSPageSize', true)
                            ->waitUntilNetworkIdle(false)
                            ->timeout(120)
                            ->newHeadless();
                    })
                    ->download($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        PdfRuntime::ensureDompdfDirectories();

        $pdf = DomPdf::loadView('admin.reservations.print', $viewData)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('fontDir', PdfRuntime::dompdfFontDirectory())
            ->setOption('fontCache', PdfRuntime::dompdfFontDirectory())
            ->setOption('tempDir', PdfRuntime::dompdfTempDirectory())
            ->setOption('isRemoteEnabled', true)
            ->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    private function pdfBranding($tenant): array
    {
        $tenant = $tenant?->loadMissing('siteSetting.files');
        $settings = $tenant ? TenantSiteSetting::forTenant($tenant) : [];
        $name = trim((string) ($settings['site_name'] ?? $tenant?->name ?? config('app.name')));

        return [
            'name' => $name !== '' ? $name : (string) config('app.name'),
            'logo' => $this->pdfImageSource($settings['logo_url'] ?? null),
        ];
    }

    private function pdfImageSource(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'data:')) {
            return $url;
        }

        $path = null;
        $pathPart = parse_url($url, PHP_URL_PATH);
        $localUrl = $pathPart ? $pathPart : $url;

        if (str_starts_with($localUrl, '/storage/')) {
            $relativeStoragePath = substr(ltrim($localUrl, '/'), strlen('storage/'));
            $path = public_path(ltrim($localUrl, '/'));
        } elseif (str_starts_with($localUrl, 'storage/')) {
            $relativeStoragePath = substr($localUrl, strlen('storage/'));
            $path = public_path($localUrl);
        } elseif (str_starts_with($localUrl, '/')) {
            $relativeStoragePath = null;
            $path = public_path(ltrim($localUrl, '/'));
        } else {
            $relativeStoragePath = $localUrl;
            $path = public_path('storage/'.ltrim($localUrl, '/'));
        }

        $paths = array_filter([
            $path,
            isset($relativeStoragePath) && $relativeStoragePath ? storage_path('app/public/'.ltrim($relativeStoragePath, '/')) : null,
        ]);

        foreach ($paths as $candidatePath) {
            if (is_file($candidatePath)) {
                $contents = file_get_contents($candidatePath);
                if (!is_string($contents) || $contents === '') {
                    return null;
                }

                $mime = mime_content_type($candidatePath) ?: 'application/octet-stream';

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            }
        }

        return $url;
    }

    public function approveExtensionRequest(Request $request): RedirectResponse
    {
        $reservationId = (int) $request->route('reservation');
        $extensionRequestId = (int) $request->route('extensionRequest');
        $reservation = Reservation::withoutGlobalScope('tenant')->findOrFail($reservationId);
        $extensionRequest = RentalExtensionRequest::withoutGlobalScope('tenant')->findOrFail($extensionRequestId);

        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($extensionRequest->reservation_id === $reservation->id, 404);

        if ($extensionRequest->status !== RentalExtensionRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'extension_request' => 'This extension request is no longer pending.',
            ]);
        }

        DB::transaction(function () use ($extensionRequest, $request, $reservation): void {
            $contract = $extensionRequest->contract()->with('reservation')->firstOrFail();
            $currentEndDate = $contract->end_date
                ? CarbonImmutable::parse($contract->end_date->toDateString())
                : null;
            if (!$currentEndDate) {
                throw ValidationException::withMessages([
                    'extension_request' => 'The contract no longer has an end date.',
                ]);
            }

            $newEndDate = CarbonImmutable::parse($extensionRequest->new_end_date)->startOfDay();
            if ($newEndDate->lessThanOrEqualTo($currentEndDate)) {
                throw ValidationException::withMessages([
                    'extension_request' => 'The requested end date is not valid anymore.',
                ]);
            }

            $extraAmount = (float) $extensionRequest->extra_amount;
            $baseContractTotal = (float) ($contract->total_amount ?? 0);
            $baseReservationTotal = (float) ($reservation->total_amount ?? 0);

            $contract->end_date = $newEndDate->toDateString();
            $contract->total_amount = round($baseContractTotal + $extraAmount, 2);
            $contract->save();

            $reservation->end_date = $newEndDate->toDateString();
            $reservation->total_days = $reservation->start_date
                ? $reservation->start_date->diffInDays($newEndDate) + 1
                : $reservation->total_days;
            $reservation->subtotal = round((float) ($reservation->subtotal ?? 0) + $extraAmount, 2);
            $reservation->total_amount = round($baseReservationTotal + $extraAmount, 2);
            $reservation->save();

            $extensionRequest->status = RentalExtensionRequestStatus::APPROVED;
            $extensionRequest->responded_by_user_id = $request->user()->id;
            $extensionRequest->responded_at = now();
            $extensionRequest->approved_at = now();
            $extensionRequest->save();

            Payment::create([
                'tenant_id' => $reservation->tenant_id,
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'amount' => number_format($extraAmount, 2, '.', ''),
                'currency' => CurrencyCatalog::codeForTenantId($reservation->tenant_id),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::PENDING,
                'notes' => 'Rental extension approved by client. Payment pending collection.',
            ]);
        });

        return redirect()
            ->route('client.reservations.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Extension request approved.');
    }

    public function rejectExtensionRequest(Request $request): RedirectResponse
    {
        $reservationId = (int) $request->route('reservation');
        $extensionRequestId = (int) $request->route('extensionRequest');
        $reservation = Reservation::withoutGlobalScope('tenant')->findOrFail($reservationId);
        $extensionRequest = RentalExtensionRequest::withoutGlobalScope('tenant')->findOrFail($extensionRequestId);

        abort_unless($reservation->user_id === $request->user()->id, 403);
        abort_unless($extensionRequest->reservation_id === $reservation->id, 404);

        if ($extensionRequest->status !== RentalExtensionRequestStatus::PENDING) {
            throw ValidationException::withMessages([
                'extension_request' => 'This extension request is no longer pending.',
            ]);
        }

        $extensionRequest->status = RentalExtensionRequestStatus::REJECTED;
        $extensionRequest->responded_by_user_id = $request->user()->id;
        $extensionRequest->responded_at = now();
        $extensionRequest->rejected_at = now();
        $extensionRequest->save();

        return redirect()
            ->route('client.reservations.index', ['subdomain' => $request->route('subdomain')])
            ->with('success', 'Extension request rejected.');
    }

    public function downloadContract(Request $request, $id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $contract = $reservation->contract;

        if (!$contract) {
            abort(404, 'No contract found for this reservation.');
        }

        // Set locale for PDF
        $supportedLocales = array_values((array) config('app.available_locales', ['en', 'ar']));
        $fallbackLocale = (string) config('app.fallback_locale', config('app.locale', 'en'));
        $requestedLocale = strtolower((string) $request->query('lang', app()->getLocale()));
        $locale = in_array($requestedLocale, $supportedLocales, true) ? $requestedLocale : $fallbackLocale;

        app()->setLocale($locale);
        \Mcamara\LaravelLocalization\Facades\LaravelLocalization::setLocale($locale);

        $contract->loadMissing([
            'reservation.user:id,name,email',
            'reservation.car:id,make,model,year,license_plate,mileage',
            'branch:id,name',
            'tenant.siteSetting.files',
            'primaryDriver.documents',
            'additionalDrivers.documents',
            'damageReports.items',
        ]);

        $reportTypeLabels = collect(\App\Support\CarDamageCatalog::reportTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $statusLabels = collect(\App\Support\CarDamageCatalog::statuses())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(\App\Support\CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(\App\Support\CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $viewSideLabels = collect(\App\Support\CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $zoneLabels = \App\Support\CarDamageCatalog::zoneLabelMap();
        $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';

        $currentDamageCases = $contract->reservation?->car?->id
            ? $this->serializeCarDamageCases((int) $contract->reservation->car->id)
            : [];
        $branding = $this->pdfBranding($contract->tenant);
        $siteSettings = $contract->tenant?->siteSetting 
            ? TenantSiteSetting::forTenant($contract->tenant) 
            : [];

        // Get PDF template from tenant settings
        $pdfTemplate = \App\Support\TenantPdfTemplateRegistry::resolveContractTemplate(
            data_get($siteSettings, 'pdf_templates.contract')
        );
        $templateView = $pdfTemplate['view'] ?? 'admin.contracts.pdf';

        $renterSignatureImage = $this->pdfImageSource($this->contractRenterSignatureUrl($contract));
        $inchargeSignatureImage = $this->pdfImageSource(data_get($siteSettings, 'contract_pdf.incharge_signature_image'));

        $viewData = [
            'contract' => $contract,
            'currentDamageCases' => $currentDamageCases,
            'damageDiagram' => $this->buildPrintableDamageDiagram($currentDamageCases, $viewSideLabels),
            'companyLogo' => $branding['logo'],
            'companyName' => $branding['name'],
            'siteSettings' => $siteSettings,
            'reportTypeLabels' => $reportTypeLabels,
            'statusLabels' => $statusLabels,
            'damageTypeLabels' => $damageTypeLabels,
            'severityLabels' => $severityLabels,
            'viewSideLabels' => $viewSideLabels,
            'zoneLabels' => $zoneLabels,
            'locale' => $locale,
            'direction' => $direction,
            'pdfTemplate' => $pdfTemplate,
            'pdfHeader' => data_get($siteSettings, 'pdf_header', []),
            'currency' => CurrencyCatalog::forTenant($contract->tenant)['symbol'],
            'currencySymbol' => CurrencyCatalog::forTenant($contract->tenant)['symbol'],
            'generatedAt' => now(),
            'renterSignatureImage' => $renterSignatureImage,
            'inchargeSignatureImage' => $inchargeSignatureImage,
        ];

        $fileName = $contract->contract_number . '.pdf';

        if (PdfRuntime::canUseBrowsershot()) {
            try {
                return SpatiePdf::view($templateView, $viewData)
                    ->format(Format::A4)
                    ->portrait()
                    ->margins(4, 4, 4, 4)
                    ->withBrowsershot(function (Browsershot $browsershot): void {
                        $nodeBinary = PdfRuntime::nodeBinary();
                        if ($nodeBinary) {
                            $browsershot->setNodeBinary($nodeBinary);
                        }

                        $npmBinary = PdfRuntime::npmBinary();
                        if ($npmBinary) {
                            $browsershot->setNpmBinary($npmBinary);
                        }

                        $chromePath = PdfRuntime::chromeBinary();
                        if ($chromePath) {
                            $browsershot->setChromePath($chromePath);
                        }
                    })
                    ->name($fileName);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Fallback to DomPDF
        return DomPdf::loadView($templateView, $viewData)
            ->setPaper('a4', 'portrait')
            ->download($fileName);
    }

    private function contractRenterSignatureUrl(\App\Models\Contract $contract): ?string
    {
        $handoverState = is_array($contract->handover_state ?? null) ? $contract->handover_state : [];
        $signature = data_get($handoverState, 'steps.terms_confirmation.payload.signature_image')
            ?? data_get($handoverState, 'phases.delivery.steps.terms_confirmation.payload.signature_image')
            ?? data_get($handoverState, 'delivery.steps.terms_confirmation.payload.signature_image');

        if (is_array($signature)) {
            return data_get($signature, 'url') ?: data_get($signature, 'file_path');
        }

        return $this->nullableString($signature);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function serializeCarDamageCases(int $carId): array
    {
        $query = \App\Models\CarDamageCase::query()
            ->where('car_id', $carId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('status', ['open', 'in_repair'])
            ->orderBy('zone_code')
            ->orderBy('id');

        $zoneLabels = \App\Support\CarDamageCatalog::zoneLabelMap();
        $viewLabels = collect(\App\Support\CarDamageCatalog::viewSides())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $damageTypeLabels = collect(\App\Support\CarDamageCatalog::damageTypes())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();
        $severityLabels = collect(\App\Support\CarDamageCatalog::severityLevels())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->all();

        return $query->get()->map(function (\App\Models\CarDamageCase $case) use ($zoneLabels, $viewLabels, $damageTypeLabels, $severityLabels) {
            return [
                'id' => $case->id,
                'zone_code' => $case->zone_code,
                'zone_label' => $zoneLabels[$case->zone_code] ?? $case->zone_code,
                'view_side' => $case->view_side,
                'view_side_label' => $viewLabels[$case->view_side] ?? $case->view_side,
                'damage_type' => $case->damage_type,
                'damage_type_label' => $damageTypeLabels[$case->damage_type] ?? $case->damage_type,
                'severity' => $case->severity,
                'severity_label' => $severityLabels[$case->severity] ?? $case->severity,
                'quantity' => (int) $case->quantity,
                'notes' => $case->notes,
                'first_detected_at' => optional($case->first_detected_at)?->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    private function buildPrintableDamageDiagram(array $damageCases, array $viewSideLabels): array
    {
        $layout = $this->printableDamageDiagramLayout();
        $zoneViews = collect(\App\Support\CarDamageCatalog::zoneViews());
        $markers = [];

        foreach (array_values($damageCases) as $index => $damage) {
            if (!is_array($damage)) {
                continue;
            }

            $zone = $zoneViews->first(function (array $view) use ($damage): bool {
                return ($view['code'] ?? null) === ($damage['zone_code'] ?? null)
                    && ($view['view_side'] ?? null) === ($damage['view_side'] ?? null);
            });

            if ($zone === null) {
                $zone = $zoneViews->first(fn (array $view): bool => ($view['code'] ?? null) === ($damage['zone_code'] ?? null));
            }

            if ($zone === null) {
                continue;
            }

            $viewSide = (string) ($zone['view_side'] ?? $damage['view_side'] ?? '');
            $viewLayout = $layout['views'][$viewSide] ?? null;
            if ($viewLayout === null) {
                continue;
            }

            $x = (float) ($zone['x'] ?? 0);
            $y = (float) ($zone['y'] ?? 0);
            $point = $this->transformPrintableDamagePoint($viewLayout, $x, $y);

            $markers[] = [
                'view_side' => $viewSide,
                'view_side_label' => $viewSideLabels[$viewSide] ?? $viewSide,
                'x' => $point['x'],
                'y' => $point['y'],
                'label' => ($index + 1),
                'damage' => $damage,
            ];
        }

        $views = [];
        foreach (array_keys($layout['views']) as $viewSide) {
            $views[$viewSide] = [
                'side' => $viewSide,
                'label' => $viewSideLabels[$viewSide] ?? $viewSide,
                'markers' => array_values(array_filter($markers, fn (array $m): bool => $m['view_side'] === $viewSide)),
            ];
        }

        $svgContent = $this->renderPrintableDamageDiagramSvg($views, $markers, $viewSideLabels);
        $dataUri = 'data:image/svg+xml;base64,'.base64_encode($svgContent);

        return [
            'views' => $views,
            'markers' => $markers,
            'svg_content' => $svgContent,
            'data_uri' => $dataUri,
        ];
    }

    private function renderPrintableDamageDiagramSvg(array $views, array $markers, array $viewSideLabels): string
    {
        $assetMarkup = $this->printableDamageDiagramAssetMarkup();
        $markerMarkup = '';

        foreach ($markers as $marker) {
            $x = (float) $marker['x'];
            $y = (float) $marker['y'];
            $label = (int) $marker['label'];
            $markerMarkup .= sprintf(
                '<g transform="translate(%s,%s)"><use href="#marker"/><text x="0" y="0" text-anchor="middle" dominant-baseline="central" fill="#fff" font-size="9" font-weight="700">%d</text></g>',
                number_format($x, 1, '.', ''),
                number_format($y, 1, '.', ''),
                $label
            );
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="738" height="483" viewBox="0 0 738 483">'.$assetMarkup.$markerMarkup.'</svg>';
    }

    private function printableDamageDiagramAssetMarkup(): string
    {
        $svgPath = public_path('img/pdf-damage-diagram-base.svg');
        if (!is_file($svgPath)) {
            return '<defs><g id="marker"><circle r="9" fill="#17306f"/></g></defs>';
        }

        $contents = file_get_contents($svgPath);
        if (!is_string($contents) || $contents === '') {
            return '<defs><g id="marker"><circle r="9" fill="#17306f"/></g></defs>';
        }

        if (preg_match('/<svg[^>]*>(.*)<\/svg>/si', $contents, $matches) === 1) {
            return $matches[1];
        }

        return $contents;
    }

    private function printableDamageDiagramLayout(): array
    {
        return [
            'views' => [
                'front' => ['x' => 52, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'cw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'top' => ['x' => 194, 'y' => 165, 'scale_x' => 1.18, 'scale_y' => 0.96, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 378, 'height' => 154],
                'rear' => ['x' => 587, 'y' => 170, 'scale_x' => 0.62, 'scale_y' => 0.62, 'rotation' => 'ccw', 'source_width' => 320, 'source_height' => 160, 'width' => 99, 'height' => 198],
                'left' => ['x' => 191, 'y' => 328, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'none', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
                'right' => ['x' => 191, 'y' => 35, 'scale_x' => 1.17, 'scale_y' => 0.78, 'rotation' => 'flip', 'source_width' => 320, 'source_height' => 160, 'width' => 374, 'height' => 125],
            ],
        ];
    }

    private function transformPrintableDamagePoint(array $viewLayout, float $x, float $y): array
    {
        $sourceWidth = (float) ($viewLayout['source_width'] ?? 320);
        $sourceHeight = (float) ($viewLayout['source_height'] ?? 160);
        $scaleX = (float) ($viewLayout['scale_x'] ?? $viewLayout['scale'] ?? 1);
        $scaleY = (float) ($viewLayout['scale_y'] ?? $viewLayout['scale'] ?? 1);
        $rotation = (string) ($viewLayout['rotation'] ?? 'none');

        $mappedX = $x;
        $mappedY = $y;

        if ($rotation === 'cw') {
            $mappedX = $sourceHeight - $y;
            $mappedY = $x;
        } elseif ($rotation === 'ccw') {
            $mappedX = $y;
            $mappedY = $sourceWidth - $x;
        } elseif ($rotation === 'flip') {
            $mappedX = $sourceWidth - $x;
            $mappedY = $sourceHeight - $y;
        }

        return [
            'x' => round((float) $viewLayout['x'] + ($mappedX * $scaleX), 1),
            'y' => round((float) $viewLayout['y'] + ($mappedY * $scaleY), 1),
        ];
    }
}
