<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Service for building AI comparison prompts.
 */
class ComparisonPromptBuilder
{
    /**
     * Build a detailed prompt for AI product comparison.
     *
     * @param Collection<int, Product> $products
     */
    public function build(Collection $products): string
    {
        $productDetails = $this->formatProductDetails($products);
        $prompt = $this->buildPromptStructure($productDetails, $products);

        return $prompt;
    }

    /**
     * Format product details for the prompt.
     *
     * @param Collection<int, Product> $products
     *
     * @return array<int, string>
     */
    private function formatProductDetails(Collection $products): array
    {
        $productDetails = [];

        foreach ($products as $product) {
            $productDetails[] = sprintf(
                "Product: %s\nBrand: %s\nPrice: %s\nCategory: %s\nDescription: %s\nYear: %s\nColors: %s",
                $product->name,
                $product->brand->name ?? 'N/A',
                $product->price ? '$' . number_format((float) $product->price, 2) : 'N/A',
                $product->category->name ?? 'N/A',
                $product->description ? Str::limit(strip_tags((string) $product->description), 300) : 'No description',
                $product->year_of_manufacture ?? 'N/A',
                is_array($product->available_colors) && count($product->available_colors) > 0
                    ? implode(', ', $product->available_colors)
                    : ($product->color_list ?? 'N/A')
            );
        }

        return $productDetails;
    }

    /**
     * Build the prompt structure with instructions.
     *
     * @param array<int, string> $productDetails
     * @param Collection<int, Product> $products
     */
    private function buildPromptStructure(array $productDetails, Collection $products): string
    {
        $prompt = "You are an expert product comparison analyst. Analyze the following products and provide a detailed comparison.\n\n";
        $prompt .= implode("\n\n---\n\n", $productDetails);
        $prompt .= "\n\nPlease provide your analysis in the following JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "pros_and_cons": {\n';

        foreach ($products as $product) {
            $prompt .= sprintf('    "%s": ["pro 1", "pro 2", "con 1", "con 2"],\n', $product->name);
        }

        $prompt .= "  },\n";
        $prompt .= '  "smart_verdict": "A concise summary and recommendation based on the comparison."\n';
        $prompt .= "}\n\n";
        $prompt .= "For each product, list 3-5 pros and 2-3 cons. The smart_verdict should be a clear, actionable recommendation (2-3 sentences).";

        return $prompt;
    }
}
