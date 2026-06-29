<?php

namespace App\Services\Reports;

use App\Core\AiExtractionQuota;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Models\AiInsightReport;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class AiInsightsOpenAiService
{
    public function analyze(AiInsightReport $report, ?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale ?: (string) ($report->locale ?? 'en'));
        $languageName = $this->languageName($locale);
        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');
        if ($provider !== 'openai') {
            throw new RuntimeException('AI Insights market study requires OpenAI as the active provider.');
        }

        if (!AiProviderSettings::isConfiguredForCurrentProvider()) {
            throw new RuntimeException('OpenAI provider is not configured. Add the API key in Super Admin settings first.');
        }

        AiExtractionQuota::ensureAvailable(TenantContext::get());

        $openAi = $settings['openai'] ?? [];
        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = max(0, min((float) ($openAi['temperature'] ?? 0.2), 1));
        $maxOutputTokens = max(3500, min((int) ($openAi['max_output_tokens'] ?? 3500), 6000));
        $payload = $this->compactPayload($report, $locale, $languageName);

        $response = $this->createResponse($model, $temperature, $maxOutputTokens, $payload, $languageName, true);
        $decoded = $this->decodeResponse($response);
        if (is_array($decoded)) {
            return $decoded;
        }

        Log::warning('AI Insights OpenAI response was not valid JSON; retrying without web search.', [
            'report_id' => $report->id,
            'locale' => $locale,
            'status' => $response->status ?? null,
            'incomplete_details' => $this->incompleteDetails($response),
            'output_preview' => mb_substr((string) ($response->outputText ?? ''), 0, 500),
        ]);

        $retryResponse = $this->createResponse($model, $temperature, $maxOutputTokens, $payload, $languageName, false);
        $retryDecoded = $this->decodeResponse($retryResponse);
        if (is_array($retryDecoded)) {
            return $retryDecoded;
        }

        Log::warning('AI Insights OpenAI retry response was not valid JSON.', [
            'report_id' => $report->id,
            'locale' => $locale,
            'status' => $retryResponse->status ?? null,
            'incomplete_details' => $this->incompleteDetails($retryResponse),
            'output_preview' => mb_substr((string) ($retryResponse->outputText ?? ''), 0, 500),
        ]);

        throw new RuntimeException('OpenAI AI Insights response is not valid JSON.');
    }

    private function createResponse(
        string $model,
        float $temperature,
        int $maxOutputTokens,
        array $payload,
        string $languageName,
        bool $useWebSearch,
    ): mixed {
        $request = [
            'model' => $model,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->systemPrompt($languageName),
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => ($useWebSearch
                                ? 'Analyze this rental business snapshot and use web search for market context in the provided market_location.'
                                : 'Analyze this rental business snapshot using only the provided internal data and market_location because web-search JSON formatting failed.'
                            )." Return every user-facing string in {$languageName}. Return strict JSON only.\n\n".
                                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'ai_insights_report',
                    'schema' => $this->schema(),
                    'strict' => true,
                ],
            ],
        ];

        if ($useWebSearch) {
            $request['tools'] = [
                ['type' => 'web_search'],
            ];
        }

        return OpenAI::responses()->create($request);
    }

    private function decodeResponse(mixed $response): ?array
    {
        if (($response->status ?? 'completed') === 'incomplete') {
            return null;
        }

        $outputText = trim((string) ($response->outputText ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('OpenAI returned an empty AI Insights response.');
        }

        $decoded = json_decode($outputText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $candidate = $this->extractJsonObject($outputText);
        if ($candidate !== null) {
            $fallback = json_decode($candidate, true);
            if (is_array($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    private function incompleteDetails(mixed $response): ?array
    {
        $details = $response->incompleteDetails ?? null;

        return is_object($details) && method_exists($details, 'toArray')
            ? $details->toArray()
            : null;
    }

    private function systemPrompt(string $languageName): string
    {
        return implode(' ', [
            'You are a car rental business analyst.',
            'Use the provided internal metrics as the source of truth for company data.',
            'Use the provided market_location as the target country, city, and business area for all market analysis.',
            'If market_location is present, do not use United States market assumptions unless the country is United States.',
            'Use web search only for general market context, demand, pricing, and rental trends in the provided market_location.',
            'Do not invent exact competitor prices unless supported by search context.',
            "Return all user-facing JSON strings in {$languageName}.",
            'Keep enum values exactly as defined in the schema.',
            'Return no more than three items in each recommendations array unless the schema asks for fewer.',
            'Keep URLs, IDs, license plates, dates, and currency values unchanged.',
            'Return concise, operational recommendations in JSON only.',
            'Avoid exposing customer emails or private personal data.',
        ]);
    }

    private function compactPayload(AiInsightReport $report, string $locale, string $languageName): array
    {
        $payload = is_array($report->internal_payload) ? $report->internal_payload : [];

        return [
            'output_language' => [
                'locale' => $locale,
                'name' => $languageName,
            ],
            'market_location' => $payload['market_location'] ?? [],
            'period' => [
                'type' => $report->period,
                'start' => $report->period_start?->toDateString(),
                'end' => $report->period_end?->toDateString(),
            ],
            'branch' => $report->branch?->name,
            'summary' => $payload['summary'] ?? [],
            'unprofitable_cars' => $this->limitRows($payload['unprofitable_cars'] ?? [], 8),
            'repeated_damage_cars' => $this->limitRows($payload['repeated_damage_cars'] ?? [], 8),
            'high_risk_customers' => $this->safeCustomerRows($payload['high_risk_customers'] ?? []),
            'price_opportunities' => $this->limitRows($payload['price_opportunities'] ?? [], 8),
            'demand_days' => $this->limitRows($payload['demand_days'] ?? [], 7),
            'uncollected_losses' => $this->limitRows($payload['uncollected_losses'] ?? [], 8),
            'problem_contracts' => $this->safeContractRows($payload['problem_contracts'] ?? []),
        ];
    }

    private function limitRows(mixed $rows, int $limit): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_slice(array_values($rows), 0, $limit);
    }

    private function safeCustomerRows(mixed $rows): array
    {
        return array_map(function (array $row): array {
            unset($row['email']);
            $row['customer_ref'] = 'customer_'.$row['customer_id'];
            unset($row['customer_id'], $row['name']);

            return $row;
        }, $this->limitRows($rows, 8));
    }

    private function safeContractRows(mixed $rows): array
    {
        return array_map(function (array $row): array {
            unset($row['customer_name']);

            return $row;
        }, $this->limitRows($rows, 8));
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'language' => ['type' => 'string'],
                'executive_summary' => ['type' => 'string'],
                'market_summary' => ['type' => 'string'],
                'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                'risks' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/insight_item'],
                ],
                'opportunities' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/insight_item'],
                ],
                'pricing_recommendations' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/insight_item'],
                ],
                'collection_actions' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/insight_item'],
                ],
                'action_plan' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/action_item'],
                ],
                'sources' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'url'],
                    ],
                ],
            ],
            'required' => [
                'language',
                'executive_summary',
                'market_summary',
                'risk_level',
                'risks',
                'opportunities',
                'pricing_recommendations',
                'collection_actions',
                'action_plan',
                'sources',
            ],
            '$defs' => [
                'insight_item' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'reason' => ['type' => 'string'],
                        'recommendation' => ['type' => 'string'],
                        'expected_impact' => ['type' => 'string'],
                    ],
                    'required' => ['title', 'severity', 'reason', 'recommendation', 'expected_impact'],
                ],
                'action_item' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'priority' => ['type' => 'string', 'enum' => ['now', 'this_week', 'this_month']],
                        'action' => ['type' => 'string'],
                        'owner' => ['type' => 'string'],
                        'metric_to_watch' => ['type' => 'string'],
                    ],
                    'required' => ['priority', 'action', 'owner', 'metric_to_watch'],
                ],
            ],
        ];
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $locale = explode('-', $locale)[0] ?: 'en';

        return in_array($locale, ['ar', 'en', 'ur'], true) ? $locale : 'en';
    }

    private function extractJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);

        for ($index = $start; $index < $length; $index++) {
            $char = $text[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function languageName(string $locale): string
    {
        return match ($locale) {
            'ar' => 'Arabic',
            'ur' => 'Urdu',
            default => 'English',
        };
    }
}
