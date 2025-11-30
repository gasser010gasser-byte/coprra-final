<?php

declare(strict_types=1);

namespace App\Services\AI\Services;

use App\Services\AI\ModelVersionTracker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Service for making HTTP requests to AI APIs with retry logic and enhanced error handling.
 */
class AIRequestService
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly int $maxRetries;
    private readonly int $retryDelay;
    private readonly LoggerInterface $logger;
    private readonly AIErrorHandlerService $errorHandler;
    private readonly ModelVersionTracker $modelTracker;

    public function __construct(
        string $apiKey,
        string $baseUrl,
        int $timeout = 60,
        int $maxRetries = 3,
        int $retryDelay = 1000,
        ?LoggerInterface $logger = null,
        ?AIErrorHandlerService $errorHandler = null,
        ?ModelVersionTracker $modelTracker = null
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->logger = $logger ?? app(LoggerInterface::class);
        $this->errorHandler = $errorHandler ?? new AIErrorHandlerService($this->logger);
        $this->modelTracker = $modelTracker ?? app(ModelVersionTracker::class);
    }

    /**
     * Make HTTP request to AI service with retry logic.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    public function makeRequest(string $endpoint, array $data, array $headers = [], string $operation = 'ai_request'): array
    {
        // Short-circuit in testing to avoid external calls
        $rawConfig = config('ai.disable_external_calls', false);
        $disableExternal = (bool) $rawConfig;
        // Log external call status in debug mode only
        if (config('app.debug')) {
            $this->logger->debug('AI request configuration', [
                'external_calls_disabled' => $disableExternal,
                'has_api_key' => !empty($this->apiKey),
                'environment' => config('app.env'),
            ]);
        }

        if ($disableExternal || empty($this->apiKey) || '0' === $this->apiKey) {
            if (!$disableExternal && !app()->runningUnitTests()) {
                $this->logger->warning('⚠️ AI API Key missing. Using mock response.');
            }
            return $this->getMockResponse($data);
        }

        // Check cost limits before making request
        $this->checkCostLimits();

        return $this->makeRequestWithRetry($endpoint, $data, $headers, $operation);
    }

    /**
     * Make request with retry logic and enhanced error handling.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     *
     * @throws \Exception
     */
    private function makeRequestWithRetry(string $endpoint, array $data, array $headers, string $operation = 'ai_request'): array
    {
        $url = $this->baseUrl . $endpoint;
        $defaultHeaders = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];

        $headers = array_merge($defaultHeaders, $headers);
        $lastException = null;

        // Extract model from data for tracking
        $model = $data['model'] ?? 'unknown';
        $startTime = microtime(true);

        for ($attempt = 1; $attempt <= $this->maxRetries; ++$attempt) {
            try {
                $this->logger->info('🤖 إرسال طلب إلى الذكاء الاصطناعي', [
                    'url' => $url,
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxRetries,
                    'timeout' => $this->timeout,
                    'operation' => $operation,
                ]);

                $response = Http::timeout($this->timeout)
                    ->withHeaders($headers)
                    ->retry($this->maxRetries, $this->retryDelay, static function ($exception, $request) {
                        // Retry on connection timeouts and 5xx errors
                        return $exception instanceof ConnectionException
                            || ($exception instanceof RequestException
                                && $exception->response
                                && $exception->response->status() >= 500);
                    })
                    ->post($url, $data)
                ;

                if ($response->successful()) {
                    $result = $response->json();
                    $responseTime = microtime(true) - $startTime;

                    // Track successful request
                    $this->modelTracker->trackUsage($model, $operation, true, $responseTime);

                    if ($attempt > 1) {
                        $this->logger->info('✅ استجابة ناجحة من الذكاء الاصطناعي بعد إعادة المحاولة', [
                            'attempt' => $attempt,
                            'result_keys' => array_keys($result),
                            'operation' => $operation,
                            'model' => $model,
                            'response_time' => $responseTime,
                        ]);
                    } else {
                        $this->logger->info('✅ استجابة ناجحة من الذكاء الاصطناعي', [
                            'result_keys' => array_keys($result),
                            'operation' => $operation,
                            'model' => $model,
                            'response_time' => $responseTime,
                        ]);
                    }

                    return $result;
                }

                // Handle non-successful responses
                $errorMessage = "AI request failed: {$response->status()} - {$response->body()}";
                $this->logger->warning('⚠️ فشل طلب الذكاء الاصطناعي', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'operation' => $operation,
                ]);

                // Don't retry on 4xx errors (client errors)
                if ($response->status() >= 400 && $response->status() < 500) {
                    throw new \Exception($errorMessage);
                }

                $lastException = new \Exception($errorMessage);
            } catch (ConnectionException $e) {
                $lastException = $e;
                $this->logger->warning('🔌 خطأ في الاتصال بالذكاء الاصطناعي', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'operation' => $operation,
                ]);
            } catch (RequestException $e) {
                $lastException = $e;
                $this->logger->warning('📡 خطأ في طلب الذكاء الاصطناعي', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'operation' => $operation,
                ]);
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->error('❌ خطأ غير متوقع في طلب الذكاء الاصطناعي', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'operation' => $operation,
                ]);

                // Check if error is recoverable using error handler
                $errorType = $this->classifyErrorType($e);
                if (!$this->errorHandler->isRecoverable($errorType)) {
                    $this->logger->warning('⚠️ Non-recoverable error detected, stopping retries', [
                        'error_type' => $errorType,
                        'operation' => $operation,
                    ]);

                    break;
                }
            }

            // Wait before retrying (except on last attempt)
            if ($attempt < $this->maxRetries) {
                $delay = $this->retryDelay * $attempt; // Exponential backoff
                $this->logger->info('⏳ انتظار قبل إعادة المحاولة', [
                    'delay_ms' => $delay,
                    'next_attempt' => $attempt + 1,
                ]);
                usleep($delay * 1000); // Convert to microseconds
            }
        }

        // All retries failed - use error handler for fallback response
        if ($lastException) {
            $responseTime = microtime(true) - $startTime;

            // Track failed request
            $this->modelTracker->trackUsage($model, $operation, false, $responseTime);

            $this->logger->error('❌ فشل جميع محاولات الاتصال بالذكاء الاصطناعي', [
                'total_attempts' => $this->maxRetries,
                'final_error' => $lastException->getMessage(),
                'operation' => $operation,
                'model' => $model,
                'response_time' => $responseTime,
            ]);

            return $this->errorHandler->handleError($lastException, $operation, [
                'endpoint' => $endpoint,
                'attempts' => $attempt - 1,
                'data' => $data,
            ]);
        }

        throw new \Exception('AI request failed after all retry attempts');
    }

    /**
     * Classify error type for recovery decision.
     */
    private function classifyErrorType(\Exception $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (
            str_contains($message, 'connection')
            || str_contains($message, 'timeout')
            || str_contains($message, 'network')
        ) {
            return 'network_error';
        }

        if (
            str_contains($message, 'unauthorized')
            || str_contains($message, 'authentication')
        ) {
            return 'authentication_error';
        }

        if (
            str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
        ) {
            return 'rate_limit_error';
        }

        if (
            method_exists($exception, 'getResponse')
            && $exception->getResponse()
            && $exception->getResponse()->status() >= 500
        ) {
            return 'service_unavailable';
        }

        return 'unknown_error';
    }

    /**
     * Generate mock response for testing.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function getMockResponse(array $data): array
    {
        $this->logger->info('🧪 تعطيل الاتصال الخارجي للذكاء الاصطناعي في الاختبارات');

        // Build input-aware mock content so tests can assert structure and values
        $userContent = $data['messages'][1]['content'] ?? ($data['messages'][0]['content'] ?? '');
        $text = '';
        $type = 'sentiment';

        if (\is_string($userContent)) {
            if (preg_match('/Analyze the following text for\s+([a-z_]+):\s*(.*)$/i', $userContent, $m)) {
                $type = strtolower(trim($m[1]));
                $text = trim($m[2]);
            } elseif (str_contains($userContent, 'User preferences:')) {
                $type = 'recommendations';
                $text = $userContent;
            } else {
                $text = $userContent;
            }
        } elseif (\is_array($userContent)) {
            // Image analysis payload uses structured content array
            $type = 'image_analysis';
            foreach ($userContent as $seg) {
                if (\is_array($seg) && (($seg['type'] ?? '') === 'text')) {
                    $text = (string) ($seg['text'] ?? '');

                    break;
                }
            }
        }

        $lc = mb_strtolower($text);
        $positiveWords = ['رائع', 'ممتاز', 'جيد', 'حب', 'جميل', 'أفضل', 'amazing', 'great', 'good', 'excellent', 'love'];
        $negativeWords = ['سيء', 'رديء', 'ضعيف', 'كريه', 'أسوأ', 'terrible', 'bad', 'poor', 'hate', 'awful'];

        $sentiment = 'neutral';
        foreach ($positiveWords as $w) {
            if (str_contains($lc, $w)) {
                $sentiment = 'positive';

                break;
            }
        }
        if ('neutral' === $sentiment) {
            foreach ($negativeWords as $w) {
                if (str_contains($lc, $w)) {
                    $sentiment = 'negative';

                    break;
                }
            }
        }

        $confidence = 'neutral' === $sentiment ? ('' === $text || '0' === $text ? 0.5 : 0.6) : 0.85;

        // Simple category inference (Arabic + English)
        $categories = [];
        if (preg_match('/هاتف|جوال|موبايل|سامسونج|أبل|إلكترونيات|laptop|phone|electronics/i', $text)) {
            $categories[] = 'إلكترونيات';
        }
        if (preg_match('/قميص|ملابس|shirt|clothing/i', $text)) {
            $categories[] = 'ملابس';
        }
        if (preg_match('/كتاب|برمجة|books?/i', $text)) {
            $categories[] = 'كتب';
        }
        if ([] === $categories) {
            $categories[] = 'عام';
        }

        // Naive keyword extraction
        $words = preg_split('/\s+/u', trim($text));
        $keywords = [];
        foreach ($words as $word) {
            $w = trim((string) $word, ".,!?:;\"'()[]{}|\\");
            if (mb_strlen($w) >= 3) {
                $keywords[] = mb_strtolower($w);
            }
        }
        $keywords = array_values(array_unique(\array_slice($keywords, 0, 5)));

        // Recommendations lines when applicable
        $recommendationLines = [];
        if ('recommendations' === $type || 'image_analysis' === $type) {
            foreach ($categories as $cat) {
                $recommendationLines[] = "recommendation: مناسب لفئة {$cat}";
            }
            if ([] === $recommendationLines) {
                $recommendationLines[] = 'recommendation: قم باختيار أفضل منتج';
            }
        }

        $lines = [];
        foreach ($categories as $cat) {
            $lines[] = "category: {$cat}";
        }
        foreach ($recommendationLines as $r) {
            $lines[] = $r;
        }
        $lines[] = "sentiment: {$sentiment}";
        $lines[] = 'confidence: ' . number_format($confidence, 2);
        foreach ($keywords as $k) {
            $lines[] = "keyword: {$k}";
        }
        if ('' !== $text && '0' !== $text) {
            $lines[] = "original_text: {$text}"; // include feedback context in result
        }

        $mockContent = implode("\n", $lines) . "\n";

        return [
            'choices' => [
                [
                    'message' => [
                        'content' => $mockContent,
                    ],
                ],
            ],
        ];
    }

    /**
     * Check if cost limits have been exceeded.
     *
     * @throws \Exception
     */
    private function checkCostLimits(): void
    {
        $dailyBudget = config('ai.daily_budget', 5.00);
        $autoStop = config('ai.auto_stop_on_budget_exceed', true);

        // Get today's cost from cache
        $todayCost = \Cache::get('ai_cost_today_' . now()->format('Y-m-d'), 0.0);

        if ($todayCost >= $dailyBudget) {
            $message = "Daily AI budget exceeded: \${$todayCost} >= \${$dailyBudget}";
            $this->logger->error('🚫 ' . $message);

            if ($autoStop) {
                throw new \Exception($message . ' - Auto-stop enabled. Requests blocked.');
            }

            $this->logger->warning('⚠️ Budget exceeded but auto-stop disabled. Request continuing...');
        }

        // Warn at 80% of budget
        $alertThreshold = config('ai.cost_alert_threshold', $dailyBudget * 0.8);
        if ($todayCost >= $alertThreshold && $todayCost < $dailyBudget) {
            $this->logger->warning('⚠️ AI cost alert', [
                'current_cost' => $todayCost,
                'daily_budget' => $dailyBudget,
                'percentage' => round(($todayCost / $dailyBudget) * 100, 2),
            ]);
        }
    }
}
