<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @internal
 *
 * @coversNothing
 */
final class SecurityTest extends TestCase
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
    public function testAuthenticationSecurityMechanisms(): void
    {
        // Test authentication security mechanisms and protections

        // Test password security requirements
        $weakPasswords = ['123', 'password', 'admin', '12345678'];
        $strongPassword = 'SecureP@ssw0rd123!';

        foreach ($weakPasswords as $weakPassword) {
            $response = $this->postJson('/api/auth/register', [
                'email' => 'test@example.com',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }

        // Test strong password acceptance
        $response = $this->postJson('/api/auth/register', [
            'email' => 'secure@example.com',
            'password' => $strongPassword,
            'password_confirmation' => $strongPassword,
        ]);

        $response->assertStatus(201);

        // Test brute force protection
        for ($i = 0; $i < 6; ++$i) {
            $this->postJson('/api/auth/login', [
                'email' => 'secure@example.com',
                'password' => 'wrong_password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'secure@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too many login attempts']);
    }

    #[Test]
    public function testAuthorizationAndAccessControls(): void
    {
        // Test authorization and access control mechanisms

        // Create test users with different roles
        $adminUser = $this->createUserWithRole('admin');
        $managerUser = $this->createUserWithRole('manager');
        $regularUser = $this->createUserWithRole('user');

        // Test admin-only endpoint access
        $adminOnlyEndpoint = '/api/admin/users';

        // Test unauthorized access
        $response = $this->getJson($adminOnlyEndpoint);
        $response->assertStatus(401);

        // Test insufficient permissions
        $response = $this->actingAs($regularUser)->getJson($adminOnlyEndpoint);
        $response->assertStatus(403);

        $response = $this->actingAs($managerUser)->getJson($adminOnlyEndpoint);
        $response->assertStatus(403);

        // Test authorized access
        $response = $this->actingAs($adminUser)->getJson($adminOnlyEndpoint);
        $response->assertStatus(200);

        // Test role-based resource access
        $managerEndpoint = '/api/manager/reports';

        $response = $this->actingAs($regularUser)->getJson($managerEndpoint);
        $response->assertStatus(403);

        $response = $this->actingAs($managerUser)->getJson($managerEndpoint);
        $response->assertStatus(200);

        $response = $this->actingAs($adminUser)->getJson($managerEndpoint);
        $response->assertStatus(200);
    }

    #[Test]
    public function testInputValidationAndSanitization(): void
    {
        // Test input validation and sanitization against common attacks

        $user = $this->createUserWithRole('user');

        // Test SQL injection attempts
        $sqlInjectionPayloads = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'/*",
            "' UNION SELECT * FROM users --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->actingAs($user)->postJson('/api/products/search', [
                'query' => $payload,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['query']);
        }

        // Test XSS prevention
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("XSS")',
            '<svg onload="alert(1)">',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->actingAs($user)->postJson('/api/profile/update', [
                'name' => $payload,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['name']);
        }

        // Test command injection prevention
        $commandInjectionPayloads = [
            '; ls -la',
            '| cat /etc/passwd',
            '&& rm -rf /',
            '`whoami`',
        ];

        foreach ($commandInjectionPayloads as $payload) {
            $response = $this->actingAs($user)->postJson('/api/files/process', [
                'filename' => $payload,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['filename']);
        }
    }

    #[Test]
    public function testSessionSecurityAndCSRFProtection(): void
    {
        // Test session security and CSRF protection mechanisms

        $user = $this->createUserWithRole('user');

        // Test CSRF protection on state-changing operations
        $response = $this->actingAs($user)->postJson('/api/profile/update', [
            'name' => 'Updated Name',
        ], [
            'X-CSRF-TOKEN' => 'invalid_token',
        ]);

        $response->assertStatus(419); // CSRF token mismatch

        // Test session fixation protection
        $this->actingAs($user);
        $initialSessionId = session()->getId();

        // Simulate login
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $newSessionId = session()->getId();
        self::assertNotSame($initialSessionId, $newSessionId);

        // Test session timeout
        $this->travel(121)->minutes(); // Exceed session timeout

        $response = $this->actingAs($user)->getJson('/api/profile');
        $response->assertStatus(401);

        // Test secure session configuration
        $sessionConfig = config('session');
        self::assertTrue($sessionConfig['secure']); // HTTPS only
        self::assertTrue($sessionConfig['http_only']); // No JavaScript access
        self::assertSame('strict', $sessionConfig['same_site']); // CSRF protection
    }

    #[Test]
    public function testDataEncryptionAndPrivacyProtection(): void
    {
        // Test data encryption and privacy protection mechanisms

        $user = $this->createUserWithRole('user');

        // Test sensitive data encryption in database
        $sensitiveData = [
            'credit_card' => '4111111111111111',
            'ssn' => '123-45-6789',
            'phone' => '+1-555-123-4567',
        ];

        $response = $this->actingAs($user)->postJson('/api/profile/sensitive', $sensitiveData);
        $response->assertStatus(200);

        // Verify data is encrypted in database
        $userRecord = \DB::table('users')->where('id', $user->id)->first();
        self::assertNotSame($sensitiveData['credit_card'], $userRecord->credit_card ?? '');
        self::assertNotSame($sensitiveData['ssn'], $userRecord->ssn ?? '');

        // Test data masking in API responses
        $response = $this->actingAs($user)->getJson('/api/profile/sensitive');
        $response->assertStatus(200);

        $responseData = $response->json();
        self::assertStringContainsString('****', $responseData['credit_card']);
        self::assertStringContainsString('***', $responseData['ssn']);

        // Test PII data deletion compliance
        $response = $this->actingAs($user)->deleteJson('/api/profile/delete-pii');
        $response->assertStatus(200);

        $userRecord = \DB::table('users')->where('id', $user->id)->first();
        self::assertNull($userRecord->credit_card ?? null);
        self::assertNull($userRecord->ssn ?? null);
    }

    #[Test]
    public function testSecurityHeadersAndHTTPSEnforcement(): void
    {
        // Test security headers and HTTPS enforcement

        $response = $this->get('/');

        // Test security headers presence
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Test Content Security Policy
        $cspHeader = $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $cspHeader);
        self::assertStringContainsString("script-src 'self'", $cspHeader);
        self::assertStringContainsString("style-src 'self'", $cspHeader);

        // Test HSTS (HTTP Strict Transport Security)
        $hstsHeader = $response->headers->get('Strict-Transport-Security');
        self::assertStringContainsString('max-age=', $hstsHeader);
        self::assertStringContainsString('includeSubDomains', $hstsHeader);

        // Test HTTPS redirect
        $httpResponse = $this->call('GET', 'http://example.com/secure-endpoint');
        self::assertSame(301, $httpResponse->getStatusCode());
        self::assertStringStartsWith('https://', $httpResponse->headers->get('Location'));
    }

    private function createUserWithRole(string $role): object
    {
        return new class ($role) {
            public $id;
            public $email;
            public $role;

            public function __construct(string $role)
            {
                $this->id = rand(1, 1000);
                $this->email = $role.'@example.com';
                $this->role = $role;
            }
        };
    }
}
