<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\AIImageAnalysisService;
use App\Services\AITextAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="AI",
 *     description="AI-powered analysis and classification endpoints"
 * )
 */
class AIController extends BaseApiController
{
    public function __construct(
        private readonly AITextAnalysisService $textAnalysisService,
        private readonly AIImageAnalysisService $imageAnalysisService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/ai/analyze",
     *     summary="Analyze text content using AI",
     *     tags={"AI"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"text"},
     *
     *             @OA\Property(property="text", type="string", description="Text to analyze")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Analysis results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="sentiment", type="string"),
     *             @OA\Property(property="keywords", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="summary", type="string"),
     *             @OA\Property(property="confidence", type="number", format="float")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|min:1|max:10000',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $result = $this->textAnalysisService->analyzeText($request->input('text'));

            return $this->success($result, 'Text analyzed successfully');
        } catch (\Exception $e) {
            return $this->serverError('Analysis failed', $e);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/ai/classify-product",
     *     summary="Classify product using AI",
     *     tags={"AI"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "description"},
     *
     *             @OA\Property(property="name", type="string", description="Product name"),
     *             @OA\Property(property="description", type="string", description="Product description"),
     *             @OA\Property(property="price", type="number", format="float", description="Product price (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Classification results",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="category", type="string"),
     *             @OA\Property(property="subcategory", type="string"),
     *             @OA\Property(property="tags", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="confidence", type="number", format="float")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function classifyProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:1|max:255',
            'description' => 'required|string|min:1|max:5000',
            'price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $result = $this->textAnalysisService->classifyProduct(
                $request->input('name'),
                $request->input('description'),
                $request->input('price')
            );

            return $this->success($result, 'Product classified successfully');
        } catch (\Exception $e) {
            return $this->serverError('Classification failed', $e);
        }
    }
}
