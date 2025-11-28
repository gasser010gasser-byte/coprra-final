<?php

declare(strict_types=1);

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // Capture release information
    // Note: exec() is disabled on Hostinger shared hosting, so we use env variable or null
    'release' => env('SENTRY_RELEASE', null),

    // Environment
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Sample rate for error events (0.0 to 1.0)
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    // Sample rate for performance monitoring (0.0 to 1.0)
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Profiles sample rate (0.0 to 1.0)
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    // Send PII (Personally Identifiable Information)
    'send_default_pii' => false,

    // Attach stack traces to messages
    'attach_stacktrace' => true,

    // Ignore specific exceptions
    'ignore_exceptions' => [
        Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        Illuminate\Auth\AuthenticationException::class,
        Illuminate\Validation\ValidationException::class,
    ],

    // Before send callback - Commented out to allow config caching
    // Note: Closures cannot be serialized for config cache
    // If needed, implement as a class method instead
    // 'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
    //     if (app()->environment('local')) {
    //         return null;
    //     }
    //     $event->setContext('app', [
    //         'version' => config('app.version', '1.0.0'),
    //         'environment' => config('app.env'),
    //     ]);
    //     return $event;
    // },
];
