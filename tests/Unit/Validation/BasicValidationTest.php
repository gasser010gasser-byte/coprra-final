<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BasicValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    #[Test]
    public function testRequiredFieldValidation(): void
    {
        // Test required field validation passes with valid data
        $validData = [
            'name' => 'Test Product',
            'email' => 'test@example.com',
            'price' => 29.99,
        ];

        $rules = [
            'name' => 'required|string|min:3',
            'email' => 'required|email',
            'price' => 'required|numeric|min:0',
        ];

        $validator = Validator::make($validData, $rules);

        self::assertFalse($validator->fails(), 'Valid data should pass validation');
        self::assertTrue($validator->passes(), 'Valid data should pass validation');
        self::assertEmpty($validator->errors()->all(), 'Valid data should have no validation errors');

        // Test required field validation fails with missing data
        $invalidData = [
            'email' => 'test@example.com',
            // Missing required 'name' and 'price' fields
        ];

        $invalidValidator = Validator::make($invalidData, $rules);

        self::assertTrue($invalidValidator->fails(), 'Missing required fields should fail validation');
        self::assertFalse($invalidValidator->passes(), 'Missing required fields should not pass validation');

        $errors = $invalidValidator->errors()->all();
        self::assertNotEmpty($errors, 'Missing required fields should generate validation errors');
        self::assertGreaterThanOrEqual(2, \count($errors), 'Should have errors for missing name and price fields');

        // Verify specific error messages
        self::assertTrue($invalidValidator->errors()->has('name'), 'Should have error for missing name field');
        self::assertTrue($invalidValidator->errors()->has('price'), 'Should have error for missing price field');
    }

    #[Test]
    public function testStringValidationRules(): void
    {
        $rules = [
            'title' => 'required|string|min:5|max:100',
            'description' => 'nullable|string|max:500',
            'slug' => 'required|string|alpha_dash|min:3',
        ];

        // Test valid string data
        $validData = [
            'title' => 'Valid Product Title',
            'description' => 'This is a valid product description.',
            'slug' => 'valid-product-slug',
        ];

        $validator = Validator::make($validData, $rules);
        self::assertTrue($validator->passes(), 'Valid string data should pass validation');
        self::assertEmpty($validator->errors()->all(), 'Valid string data should have no errors');

        // Test invalid string data - too short title
        $invalidData1 = [
            'title' => 'Hi', // Too short (min:5)
            'description' => 'Valid description',
            'slug' => 'valid-slug',
        ];

        $invalidValidator1 = Validator::make($invalidData1, $rules);
        self::assertTrue($invalidValidator1->fails(), 'Too short title should fail validation');
        self::assertTrue($invalidValidator1->errors()->has('title'), 'Should have error for short title');

        // Test invalid string data - too long description
        $invalidData2 = [
            'title' => 'Valid Title Here',
            'description' => str_repeat('a', 501), // Too long (max:500)
            'slug' => 'valid-slug',
        ];

        $invalidValidator2 = Validator::make($invalidData2, $rules);
        self::assertTrue($invalidValidator2->fails(), 'Too long description should fail validation');
        self::assertTrue($invalidValidator2->errors()->has('description'), 'Should have error for long description');

        // Test invalid slug with special characters
        $invalidData3 = [
            'title' => 'Valid Title Here',
            'description' => 'Valid description',
            'slug' => 'invalid slug with spaces!', // Invalid characters for alpha_dash
        ];

        $invalidValidator3 = Validator::make($invalidData3, $rules);
        self::assertTrue($invalidValidator3->fails(), 'Invalid slug should fail validation');
        self::assertTrue($invalidValidator3->errors()->has('slug'), 'Should have error for invalid slug');
    }

    #[Test]
    public function testNumericAndEmailValidation(): void
    {
        $rules = [
            'price' => 'required|numeric|min:0|max:9999.99',
            'quantity' => 'required|integer|min:1|max:1000',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url',
        ];

        // Test valid numeric and email data
        $validData = [
            'price' => 99.99,
            'quantity' => 50,
            'email' => 'valid@example.com',
            'website' => 'https://example.com',
        ];

        $validator = Validator::make($validData, $rules);
        self::assertTrue($validator->passes(), 'Valid numeric and email data should pass validation');
        self::assertEmpty($validator->errors()->all(), 'Valid data should have no validation errors');

        // Test invalid price (negative)
        $invalidData1 = [
            'price' => -10.50, // Negative price
            'quantity' => 50,
            'email' => 'valid@example.com',
            'website' => 'https://example.com',
        ];

        $invalidValidator1 = Validator::make($invalidData1, $rules);
        self::assertTrue($invalidValidator1->fails(), 'Negative price should fail validation');
        self::assertTrue($invalidValidator1->errors()->has('price'), 'Should have error for negative price');

        // Test invalid email format
        $invalidData2 = [
            'price' => 99.99,
            'quantity' => 50,
            'email' => 'invalid-email-format', // Invalid email
            'website' => 'https://example.com',
        ];

        $invalidValidator2 = Validator::make($invalidData2, $rules);
        self::assertTrue($invalidValidator2->fails(), 'Invalid email should fail validation');
        self::assertTrue($invalidValidator2->errors()->has('email'), 'Should have error for invalid email');

        // Test invalid URL format
        $invalidData3 = [
            'price' => 99.99,
            'quantity' => 50,
            'email' => 'valid@example.com',
            'website' => 'not-a-valid-url', // Invalid URL
        ];

        $invalidValidator3 = Validator::make($invalidData3, $rules);
        self::assertTrue($invalidValidator3->fails(), 'Invalid URL should fail validation');
        self::assertTrue($invalidValidator3->errors()->has('website'), 'Should have error for invalid URL');

        // Test quantity out of range
        $invalidData4 = [
            'price' => 99.99,
            'quantity' => 1001, // Exceeds max:1000
            'email' => 'valid@example.com',
            'website' => 'https://example.com',
        ];

        $invalidValidator4 = Validator::make($invalidData4, $rules);
        self::assertTrue($invalidValidator4->fails(), 'Quantity exceeding max should fail validation');
        self::assertTrue($invalidValidator4->errors()->has('quantity'), 'Should have error for quantity exceeding max');
    }

    #[Test]
    public function testArrayAndFileValidation(): void
    {
        // Create categories that will be referenced in validation
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $category3 = Category::factory()->create();

        $rules = [
            'tags' => 'required|array|min:1|max:5',
            'tags.*' => 'string|max:50',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'image' => 'nullable|string|max:255', // Simulating file path validation
        ];

        // Test valid array data
        $validData = [
            'tags' => ['electronics', 'gadgets', 'tech'],
            'categories' => [$category1->id, $category2->id, $category3->id],
            'image' => '/uploads/products/image.jpg',
        ];

        $validator = Validator::make($validData, $rules);
        self::assertTrue($validator->passes(), 'Valid array data should pass validation');
        self::assertEmpty($validator->errors()->all(), 'Valid array data should have no errors');

        // Test invalid array data - empty tags array
        $invalidData1 = [
            'tags' => [], // Empty array (min:1)
            'categories' => [$category1->id, $category2->id],
            'image' => '/uploads/products/image.jpg',
        ];

        $invalidValidator1 = Validator::make($invalidData1, $rules);
        self::assertTrue($invalidValidator1->fails(), 'Empty tags array should fail validation');
        self::assertTrue($invalidValidator1->errors()->has('tags'), 'Should have error for empty tags array');

        // Test invalid array data - too many tags
        $invalidData2 = [
            'tags' => ['tag1', 'tag2', 'tag3', 'tag4', 'tag5', 'tag6'], // Exceeds max:5
            'categories' => [$category1->id, $category2->id],
            'image' => '/uploads/products/image.jpg',
        ];

        $invalidValidator2 = Validator::make($invalidData2, $rules);
        self::assertTrue($invalidValidator2->fails(), 'Too many tags should fail validation');
        self::assertTrue($invalidValidator2->errors()->has('tags'), 'Should have error for too many tags');

        // Test invalid tag content - too long
        $invalidData3 = [
            'tags' => ['valid-tag', str_repeat('a', 51)], // One tag too long (max:50)
            'categories' => [$category1->id, $category2->id],
            'image' => '/uploads/products/image.jpg',
        ];

        $invalidValidator3 = Validator::make($invalidData3, $rules);
        self::assertTrue($invalidValidator3->fails(), 'Too long tag should fail validation');
        self::assertTrue($invalidValidator3->errors()->has('tags.1'), 'Should have error for specific long tag');
    }

    #[Test]
    public function testConditionalValidation(): void
    {
        $rules = [
            'type' => 'required|in:physical,digital',
            'weight' => 'required_if:type,physical|nullable|numeric|min:0',
            'download_url' => 'required_if:type,digital|nullable|url',
            'shipping_required' => 'boolean',
            'shipping_cost' => 'required_if:shipping_required,true|nullable|numeric|min:0',
        ];

        // Test valid physical product
        $physicalProductData = [
            'type' => 'physical',
            'weight' => 1.5,
            'download_url' => null,
            'shipping_required' => true,
            'shipping_cost' => 9.99,
        ];

        $validator1 = Validator::make($physicalProductData, $rules);
        self::assertTrue($validator1->passes(), 'Valid physical product should pass validation');
        self::assertEmpty($validator1->errors()->all(), 'Valid physical product should have no errors');

        // Test valid digital product
        $digitalProductData = [
            'type' => 'digital',
            'weight' => null,
            'download_url' => 'https://example.com/download/file.zip',
            'shipping_required' => false,
            'shipping_cost' => null,
        ];

        $validator2 = Validator::make($digitalProductData, $rules);
        self::assertTrue($validator2->passes(), 'Valid digital product should pass validation');
        self::assertEmpty($validator2->errors()->all(), 'Valid digital product should have no errors');

        // Test invalid physical product - missing weight
        $invalidPhysicalData = [
            'type' => 'physical',
            'weight' => null, // Required for physical products
            'download_url' => null,
            'shipping_required' => false,
            'shipping_cost' => null,
        ];

        $invalidValidator1 = Validator::make($invalidPhysicalData, $rules);
        self::assertTrue($invalidValidator1->fails(), 'Physical product without weight should fail validation');
        self::assertTrue($invalidValidator1->errors()->has('weight'), 'Should have error for missing weight');

        // Test invalid digital product - missing download URL
        $invalidDigitalData = [
            'type' => 'digital',
            'weight' => null,
            'download_url' => null, // Required for digital products
            'shipping_required' => false,
            'shipping_cost' => null,
        ];

        $invalidValidator2 = Validator::make($invalidDigitalData, $rules);
        self::assertTrue($invalidValidator2->fails(), 'Digital product without download URL should fail validation');
        self::assertTrue($invalidValidator2->errors()->has('download_url'), 'Should have error for missing download URL');

        // Test shipping cost required when shipping is required
        $invalidShippingData = [
            'type' => 'physical',
            'weight' => 1.0,
            'download_url' => null,
            'shipping_required' => true,
            'shipping_cost' => null, // Required when shipping_required is true
        ];

        $invalidValidator3 = Validator::make($invalidShippingData, $rules);
        self::assertTrue($invalidValidator3->fails(), 'Should require shipping cost when shipping is required');
        self::assertTrue($invalidValidator3->errors()->has('shipping_cost'), 'Should have error for missing shipping cost');
    }

    #[Test]
    public function testCustomValidationMessages(): void
    {
        $rules = [
            'product_name' => 'required|string|min:3|max:100',
            'price' => 'required|numeric|min:0.01',
        ];

        $customMessages = [
            'product_name.required' => 'Product name is mandatory.',
            'product_name.min' => 'Product name must be at least 3 characters long.',
            'price.required' => 'Price is required for all products.',
            'price.min' => 'Price must be greater than zero.',
        ];

        // Test invalid data to trigger custom messages
        $invalidData = [
            'product_name' => 'Hi', // Too short
            'price' => 0, // Below minimum
        ];

        $validator = Validator::make($invalidData, $rules, $customMessages);

        self::assertTrue($validator->fails(), 'Invalid data should fail validation');

        $errors = $validator->errors();

        // Verify custom messages are used
        self::assertTrue($errors->has('product_name'), 'Should have error for product_name');
        self::assertTrue($errors->has('price'), 'Should have error for price');

        $productNameErrors = $errors->get('product_name');
        $priceErrors = $errors->get('price');

        self::assertContains(
            'Product name must be at least 3 characters long.',
            $productNameErrors,
            'Should use custom message for product name min length'
        );
        self::assertContains(
            'Price must be greater than zero.',
            $priceErrors,
            'Should use custom message for price minimum'
        );

        // Verify error count
        self::assertCount(2, $errors->keys(), 'Should have exactly 2 validation errors');
        self::assertGreaterThan(0, \count($productNameErrors), 'Should have at least one product name error');
        self::assertGreaterThan(0, \count($priceErrors), 'Should have at least one price error');
    }
}
