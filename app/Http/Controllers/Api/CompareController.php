<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AI\ComparisonPromptBuilder;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompareController extends Controller
{
    private const SESSION_KEY = 'compare';
    private const MAX_ITEMS = 4;

    public function __construct(
        private readonly ComparisonPromptBuilder $promptBuilder
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $ids = $this->getCompareIds($request);

        return response()->json([
            'success' => true,
            'items' => $ids,
            'count' => \count($ids),
            'limit' => self::MAX_ITEMS,
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $ids = $this->getCompareIds($request);

        if (\in_array($product->id, $ids, true)) {
            return response()->json([
                'success' => true,
                'message' => __('This product is already in your comparison list.'),
                'count' => \count($ids),
                'items' => $ids,
                'added' => false,
            ]);
        }

        if (\count($ids) >= self::MAX_ITEMS) {
            return response()->json([
                'success' => false,
                'message' => __('Comparison limit reached. You can only compare up to :count products.', ['count' => self::MAX_ITEMS]),
                'count' => \count($ids),
                'items' => $ids,
            ], 409);
        }

        $ids[] = $product->id;
        $request->session()->put(self::SESSION_KEY, $ids);

        return response()->json([
            'success' => true,
            'message' => __('Product added to comparison.'),
            'count' => \count($ids),
            'items' => $ids,
            'added' => true,
            'product' => $this->transformProduct($product),
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $ids = $this->getCompareIds($request);
        $initial = \count($ids);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $product->id));

        if (\count($ids) === $initial) {
            return response()->json([
                'success' => false,
                'message' => __('Product was not in your comparison list.'),
                'count' => $initial,
                'items' => $ids,
            ], 404);
        }

        $request->session()->put(self::SESSION_KEY, $ids);

        return response()->json([
            'success' => true,
            'message' => __('Product removed from comparison.'),
            'count' => \count($ids),
            'items' => $ids,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json([
            'success' => true,
            'message' => __('Comparison list cleared.'),
            'count' => 0,
            'items' => [],
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function getCompareIds(Request $request): array
    {
        /** @var array<int, int>|null $ids */
        $ids = $request->session()->get(self::SESSION_KEY);

        if (! \is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Analyze products using AI to generate pros/cons and smart verdict.
     */
    public function analyze(Request $request, AIService $aiService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_ids' => 'required|array|min:2|max:4',
                'product_ids.*' => 'required|integer|exists:products,id',
            ]);

            /** @var array<int, int> $productIds */
            $productIds = $validated['product_ids'];

            // Fetch products with relationships
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->with(['category', 'brand'])
                ->get();

            if ($products->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => __('At least 2 products are required for comparison.'),
                ], 422);
            }

            // Build detailed prompt for AI analysis
            $prompt = $this->promptBuilder->build($products);

            // Call AI service with custom prompt
            $aiResult = $aiService->analyzeText($prompt, [
                'type' => 'product_comparison',
                'max_tokens' => 1000,
            ]);

            // Parse AI response to extract pros_and_cons and smart_verdict
            $analysis = $this->parseAIResponse($aiResult, $products);

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'message' => __('AI analysis completed successfully.'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => __('Validation failed.'),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('AI Compare Analysis Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('Failed to generate AI analysis. Please try again later.'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Parse AI response and extract structured data.
     *
     * @param array<string, mixed> $aiResult
     * @param \Illuminate\Database\Eloquent\Collection<int, Product> $products
     *
     * @return array<string, mixed>
     */
    private function parseAIResponse(array $aiResult, $products): array
    {
        $result = $aiResult['result'] ?? '';

        // Try to extract JSON from the response
        $jsonMatch = [];
        if (preg_match('/\{[\s\S]*\}/', $result, $jsonMatch)) {
            try {
                $parsed = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE && isset($parsed['pros_and_cons']) && isset($parsed['smart_verdict'])) {
                    return [
                        'pros_and_cons' => $parsed['pros_and_cons'],
                        'smart_verdict' => $parsed['smart_verdict'],
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to parse AI JSON response', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: Generate structured response from text
        $prosAndCons = [];
        foreach ($products as $product) {
            $prosAndCons[$product->name] = [
                'Pros: ' . ($product->price ? 'Good value at $' . number_format((float) $product->price, 2) : 'Available'),
                'Cons: Limited information available',
            ];
        }

        return [
            'pros_and_cons' => $prosAndCons,
            'smart_verdict' => $result ?: 'Based on the available information, compare the products based on your specific needs and preferences.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'image' => $product->image,
            'price' => $product->price,
            'brand' => $product->brand?->name,
        ];
    }
}
