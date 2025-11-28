<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\Services\AIImageAnalysisService;
use App\Services\AI\Services\AIRequestService;
use App\Services\AI\Services\AITextAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class AIControlPanelController extends Controller
{
    public function __construct(
        private readonly AITextAnalysisService $textAnalysisService,
        private readonly AIImageAnalysisService $imageAnalysisService,
        private readonly AIRequestService $aiRequestService
    ) {
    }

    /**
     * Display the AI control panel dashboard.
     */
    public function index(): View
    {
        return view('admin.ai-control-panel', [
            'title' => 'AI Control Panel',
            'status' => $this->getAIStatus(),
        ]);
    }

    /**
     * Get AI system status.
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->getAIStatus();

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze text content.
     */
    public function analyzeText(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->textAnalysisService->analyzeText($request->input('text'));

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Text analysis completed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Text analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Classify product.
     */
    public function classifyProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1|max:255',
            'description' => 'required|string|min:1|max:5000',
            'price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->textAnalysisService->classifyProduct(
                $request->input('name'),
                $request->input('description'),
                $request->input('price')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Product classification completed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product classification failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate recommendations.
     */
    public function generateRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_preferences' => 'required|array',
            'products' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->textAnalysisService->generateRecommendations(
                $request->input('user_preferences'),
                $request->input('products')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Recommendations generated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Recommendation generation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze image.
     */
    public function analyzeImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|url|max:2048',
            'prompt' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->imageAnalysisService->analyzeImage(
                $request->input('image_url'),
                $request->input('prompt', 'Analyze this product image and provide details about the product.')
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Image analysis completed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image analysis failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get AI system status information.
     */
    private function getAIStatus(): array
    {
        return [
            'services' => [
                'text_analysis' => [
                    'status' => 'active',
                    'model' => config('ai.models.text', 'gpt-3.5-turbo'),
                    'last_check' => now()->toISOString(),
                ],
                'image_analysis' => [
                    'status' => 'active',
                    'model' => config('ai.models.image', 'gpt-4-vision-preview'),
                    'last_check' => now()->toISOString(),
                ],
                'monitoring' => [
                    'status' => config('ai.monitor.enabled', false) ? 'active' : 'disabled',
                    'last_check' => now()->toISOString(),
                ],
                'strict_agent' => [
                    'status' => config('ai.strict_agent.enabled', false) ? 'active' : 'disabled',
                    'last_check' => now()->toISOString(),
                ],
            ],
            'configuration' => [
                'api_timeout' => config('ai.timeout', 30),
                'max_tokens' => config('ai.max_tokens', 1000),
                'temperature' => config('ai.temperature', 0.7),
                'cache_enabled' => config('ai.cache.enabled', true),
                'cache_ttl' => config('ai.cache.ttl', 3600),
            ],
            'system_info' => [
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'version' => '1.0.0',
            ],
        ];
    }
}
