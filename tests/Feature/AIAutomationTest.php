<?php

namespace Tests\Feature;

use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIAutomationTest extends TestCase
{
    /**
     * Test that the AI service can analyze text.
     *
     * @return void
     */
    public function test_ai_can_analyze_text()
    {
        // Mock the HTTP client to avoid real API calls
        // The service parses the content string, so we need to provide parseable content
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "sentiment: positive\nconfidence: 0.85\ncategory: technology\nkeyword: ai\nkeyword: machine learning"
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Get the service from the container
        $aiService = $this->app->make(AIService::class);

        $text = "This is a test text about artificial intelligence and machine learning.";

        // Call the method to be tested
        $result = $aiService->analyzeText($text);

        // Assert that the result is what we expect
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('keywords', $result);
        // Sentiment should be a valid string (positive, negative, or neutral)
        $this->assertContains($result['sentiment'], ['positive', 'negative', 'neutral']);
        $this->assertIsFloat($result['confidence']);
    }

    /**
     * Test that the AI service can classify a product.
     *
     * @return void
     */
    public function test_ai_can_classify_product()
    {
        // Mock the HTTP client
        // The service expects Arabic categories and parses content string
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "category: إلكترونيات\nsubcategory: smartphones\nconfidence: 0.95\ntag: mobile\ntag: technology"
                        ]
                    ]
                ]
            ], 200)
        ]);

        // Get the service from the container
        $aiService = $this->app->make(AIService::class);

        $productDescription = "Latest smartphone with advanced AI features and high-resolution camera.";

        // Call the method to be tested
        $result = $aiService->classifyProduct($productDescription);

        // Assert that the result is what we expect
        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('subcategory', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('tags', $result);
        // Category should be in Arabic (إلكترونيات = Electronics)
        $this->assertNotEmpty($result['category']);
        $this->assertIsFloat($result['confidence']);
        $this->assertGreaterThan(0.0, $result['confidence']);
    }
}
