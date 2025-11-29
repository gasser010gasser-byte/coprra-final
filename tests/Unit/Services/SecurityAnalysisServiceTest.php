<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SecurityAnalysisService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SecurityAnalysisServiceTest extends TestCase
{
    private SecurityAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SecurityAnalysisService();
    }

    public function testAnalyzeReturnsCorrectStructure(): void
    {
        $result = $this->service->analyze();

        self::assertIsArray($result);
        self::assertArrayHasKey('score', $result);
        self::assertArrayHasKey('max_score', $result);
        self::assertArrayHasKey('issues', $result);
        self::assertArrayHasKey('category', $result);
        self::assertSame(100, $result['max_score']);
        self::assertSame('Security', $result['category']);
        self::assertIsInt($result['score']);
        self::assertIsArray($result['issues']);
        self::assertGreaterThanOrEqual(0, $result['score']);
        self::assertLessThanOrEqual(100, $result['score']);
    }

    public function testAnalyzeWithDebugModeEnabled(): void
    {
        Config::set('app.debug', true);

        $result = $this->service->analyze();

        self::assertContains('Debug mode is enabled (should be false in production)', $result['issues']);
        self::assertLessThan(100, $result['score']);
    }

    public function testAnalyzeWithDebugModeDisabled(): void
    {
        Config::set('app.debug', false);

        $result = $this->service->analyze();

        self::assertNotContains('Debug mode is enabled (should be false in production)', $result['issues']);
    }

    public function testAnalyzeWithHttpUrl(): void
    {
        Config::set('app.url', 'http://example.com');

        $result = $this->service->analyze();

        self::assertContains('HTTPS not configured in APP_URL', $result['issues']);
        self::assertLessThan(100, $result['score']);
    }

    public function testAnalyzeWithHttpsUrl(): void
    {
        Config::set('app.url', 'https://secure-example.com');

        $result = $this->service->analyze();

        self::assertNotContains('HTTPS not configured in APP_URL', $result['issues']);
    }

    public function testAnalyzeWithEmptyAppUrl(): void
    {
        // Use config() helper to set value, then Config::set to ensure facade is updated
        config(['app.url' => '']);
        Config::set('app.url', '');

        $result = $this->service->analyze();

        self::assertContains('HTTPS not configured in APP_URL', $result['issues']);
    }

    public function testAnalyzeWithNullAppUrl(): void
    {
        // Use config() helper to set value, then Config::set to ensure facade is updated
        config(['app.url' => null]);
        Config::set('app.url', null);

        $result = $this->service->analyze();

        self::assertContains('HTTPS not configured in APP_URL', $result['issues']);
    }

    public function testAnalyzeChecksEnvironmentFile(): void
    {
        $result = $this->service->analyze();

        // Check if .env.example exists in the project
        $envExampleExists = file_exists(base_path('.env.example'));

        if ($envExampleExists) {
            self::assertNotContains('.env.example file missing', $result['issues']);
        } else {
            self::assertContains('.env.example file missing', $result['issues']);
        }
    }

    public function testAnalyzeScoreIsWithinValidRange(): void
    {
        $result = $this->service->analyze();

        self::assertGreaterThanOrEqual(0, $result['score']);
        self::assertLessThanOrEqual(100, $result['score']);
        self::assertTrue($result['score'] % 10 === 0, 'Score should be a multiple of 10');
    }

    public function testAnalyzeIssuesAreStrings(): void
    {
        $result = $this->service->analyze();

        foreach ($result['issues'] as $issue) {
            self::assertIsString($issue);
            self::assertNotEmpty($issue);
        }
    }

    public function testAnalyzeConsistentResults(): void
    {
        $result1 = $this->service->analyze();
        $result2 = $this->service->analyze();

        // Results should be consistent when run multiple times
        self::assertSame($result1['score'], $result2['score']);
        self::assertSame($result1['issues'], $result2['issues']);
        self::assertSame($result1['category'], $result2['category']);
        self::assertSame($result1['max_score'], $result2['max_score']);
    }
}
