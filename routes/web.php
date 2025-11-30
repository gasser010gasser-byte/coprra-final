<?php

declare(strict_types=1);

use App\Http\Controllers\Account\WishlistController as AccountWishlistController;
use App\Http\Controllers\Admin\AgentDashboardController;
use App\Http\Controllers\Admin\AgentManagementController;
use App\Http\Controllers\Admin\AIControlPanelController;
use App\Http\Controllers\Admin\ScraperController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AI\AgentHealthController;
use App\Http\Controllers\Api\CompareController as ApiCompareController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CostDashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PointsController;
use App\Http\Controllers\PriceAlertController;
use App\Http\Controllers\PriceComparisonController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Pulse;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- المسارات العامة التي لا تتطلب تسجيل الدخول ---

// الصفحة الرئيسية
// Health check routes (comprehensive monitoring)
Route::get('/health', [HealthController::class, 'index'])->name('health.index');
Route::get('/health/check', [HealthController::class, 'check'])->name('health.check');
Route::get('/health/ping', [HealthController::class, 'ping'])->name('health.ping');

// Legacy health-check route: redirect to unified health endpoint
Route::get('/health-check', static function () {
    return redirect('/health');
})->name('health.legacy');

// Status monitoring endpoint
Route::get('/status', [StatusController::class, 'index'])->name('status');

// API-like routes that rely on session state (guest-friendly endpoints)
Route::prefix('api')->name('api.')->group(static function (): void {
    Route::get('compare', [ApiCompareController::class, 'index'])->name('compare.index');
    Route::post('compare/clear', [ApiCompareController::class, 'clear'])->name('compare.clear');
    Route::post('compare/{product}', [ApiCompareController::class, 'store'])->name('compare.store');
    Route::delete('compare/{product}', [ApiCompareController::class, 'destroy'])->name('compare.destroy');
});

// Laravel Pulse dashboard (secured via viewPulse gate and middleware in config/pulse.php)
if (class_exists(\Laravel\Pulse\Pulse::class) && method_exists(\Laravel\Pulse\Pulse::class, 'route')) {
    Pulse::route('/' . (string) config('pulse.path', 'pulse'));
}

// Cost Dashboard (requires authentication)
Route::get('/dashboard/costs', [CostDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard.costs')
;

// AI Agent Health Monitoring Routes
Route::prefix('ai/health')->name('ai.health.')->group(static function () {
    // General health status
    Route::get('/', [AgentHealthController::class, 'healthStatus'])->name('status');
    Route::get('/stats', [AgentHealthController::class, 'lifecycleStats'])->name('stats');
    Route::get('/errors', [AgentHealthController::class, 'errorSummary'])->name('errors');

    // Agent-specific health
    Route::get('/agent/{agentId}', [AgentHealthController::class, 'agentHealth'])->name('agent');
    Route::post('/agent/{agentId}/heartbeat', [AgentHealthController::class, 'recordHeartbeat'])->name('heartbeat');

    // Agent lifecycle management
    Route::post('/agent/{agentId}/pause', [AgentHealthController::class, 'pauseAgent'])->name('pause');
    Route::post('/agent/{agentId}/resume', [AgentHealthController::class, 'resumeAgent'])->name('resume');
    Route::post('/agent/{agentId}/initialize', [AgentHealthController::class, 'initializeAgent'])->name('initialize');
    Route::post('/agents/recover', [AgentHealthController::class, 'recoverFailedAgents'])->name('recover');

    // Circuit breaker management
    Route::get('/circuit-breaker', [AgentHealthController::class, 'circuitBreakerStatus'])->name('circuit.status');
    Route::post('/circuit-breaker/{serviceName}/reset', [AgentHealthController::class, 'resetCircuitBreaker'])->name('circuit.reset');

    // State recovery and corruption management
    Route::post('/agents/{agentId}/recover-state', [AgentHealthController::class, 'recoverAgentState']);
    Route::get('/agents/{agentId}/detect-corruption', [AgentHealthController::class, 'detectStateCorruption']);
    Route::post('/agents/auto-recovery', [AgentHealthController::class, 'performAutomaticRecovery']);
    Route::post('/agents/graceful-shutdown', [AgentHealthController::class, 'initiateGracefulShutdown']);
});

Route::get('/', [HomeController::class, 'index'])->name('home');

// SEO: dynamic sitemap
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Static Pages
Route::get('/about', fn() => view('about'))->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/privacy', fn() => view('privacy'))->name('privacy');
Route::get('/terms', fn() => view('terms'))->name('terms');
Route::get('/faq', fn() => view('faq'))->name('faq');
Route::get('/stores', [\App\Http\Controllers\StoresController::class, 'index'])->name('stores.index');

// Dashboard route expected by tests
Route::get('/dashboard', static function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

// Authentication routes - Using Controllers with Form Requests and Rate Limiting
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.post');

// Registration routes - DISABLED (view shows registration is disabled)
// Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
// Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register.post');

// Alias route for password reset request expected by tests
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->middleware('throttle:3,1')->name('password.forgot');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Password reset routes with Rate Limiting
Route::get('/password/reset', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/password/reset/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1')->name('password.update');

// Email verification routes with Rate Limiting
Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->middleware('auth')->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

// Social Login routes
Route::get('/auth/google/redirect', [SocialLoginController::class, 'redirectToGoogle'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [SocialLoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/facebook/redirect', [SocialLoginController::class, 'redirectToFacebook'])->name('auth.facebook.redirect');
Route::get('/auth/facebook/callback', [SocialLoginController::class, 'handleFacebookCallback'])->name('auth.facebook.callback');

// المنتجات والفئات
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
// Price comparison routes must come before the generic product show route
Route::get('products/{product}/price-comparison', [PriceComparisonController::class, 'show'])->name('products.price-comparison');
Route::post('products/{product}/price-comparison/refresh', [PriceComparisonController::class, 'refresh'])->name('products.price-comparison.refresh');
Route::get('products/{slug}', [ProductController::class, 'show'])->name('products.show');

// Compare Routes (public, session-based)
Route::get('compare', [CompareController::class, 'index'])->name('compare.index');
Route::post('compare/add/{product}', [CompareController::class, 'add'])->name('compare.add');
Route::delete('compare/remove/{product}', [CompareController::class, 'remove'])->name('compare.remove');
Route::post('compare/clear', [CompareController::class, 'clear'])->name('compare.clear');

Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

// تغيير اللغة والعملة
Route::get('language/{langCode}', [LocaleController::class, 'changeLanguage'])->name('change.language');
Route::get('currency/{currencyCode}', [LocaleController::class, 'changeCurrency'])->name('change.currency');
Route::get('country/{countryCode}', [LocaleController::class, 'changeCountry'])->name('change.country');

// Locale switching routes
Route::post('locale/language', [LocaleController::class, 'switchLanguage'])->name('locale.language');
Route::post('locale/currency', [LocaleController::class, 'switchCurrency'])->name('locale.currency');
Route::post('locale/country', [LocaleController::class, 'switchCountry'])->name('locale.country');

// --- المسارات المحمية التي تتطلب تسجيل الدخول ---

Route::middleware('auth')->group(static function (): void {
    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

    // Price Alert Routes (من الكود الخاص بك، وهو مثالي)
    Route::patch('price-alerts/{priceAlert}/toggle', [PriceAlertController::class, 'toggle'])->name('price-alerts.toggle');
    Route::resource('price-alerts', PriceAlertController::class)->parameters([
        'price-alerts' => 'priceAlert',
    ]);

    Route::get('account/wishlist', [AccountWishlistController::class, 'index'])->name('account.wishlist');

    // Points & Rewards Routes
    Route::get('account/points', [PointsController::class, 'index'])->name('account.points');
    Route::post('points/redeem', [PointsController::class, 'redeem'])->name('points.redeem');
    Route::post('points/redeem-reward/{reward}', [PointsController::class, 'redeemReward'])->name('points.redeemReward');

    // Review Routes
    Route::get('products/{product}/reviews/create', [ReviewController::class, 'create'])->name('reviews.create');
    Route::get('reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::resource('reviews', ReviewController::class)->only(['store', 'update', 'destroy']);

    // (Moved to public routes)
});

// Cart Routes (public, ensure web middleware is explicitly applied)
Route::middleware('web')->group(static function (): void {
    Route::get('cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('cart', [CartController::class, 'addFromRequest'])->name('cart.store');
    Route::post('cart/add/{product}', [CartController::class, 'add'])->name('cart.add');
    Route::post('cart/update', [CartController::class, 'update'])->name('cart.update');
    Route::delete('cart/remove/{itemId}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('cart/clear', [CartController::class, 'clear'])->name('cart.clear');
});


// --- Admin Routes (تتطلب صلاحيات إدارية) ---

Route::middleware(['auth', \App\Http\Middleware\IsAdmin::class])->prefix('admin')->name('admin.')->group(static function (): void {
    // Root admin route - redirect to dashboard
    Route::get('/', fn() => redirect()->route('admin.dashboard'));

    // Dashboard and basic management pages
    Route::get('dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('users', [AdminController::class, 'users'])->name('users');
    Route::get('users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::get('stores', [AdminController::class, 'stores'])->name('stores');
    Route::post('users/{user}/toggle-admin', [AdminController::class, 'toggleUserAdmin'])->name('users.toggle-admin');

    // Products routes
    Route::prefix('products')->name('products.')->group(static function (): void {
        Route::get('/', [AdminController::class, 'products'])->name('index');
        Route::get('/{product}/edit', [AdminController::class, 'editProduct'])->name('edit');
        Route::put('/{product}', [AdminController::class, 'updateProduct'])->name('update');
    });

    // Categories routes
    Route::prefix('categories')->name('categories.')->group(static function (): void {
        Route::get('/', [AdminController::class, 'categories'])->name('index');
        Route::get('/{category}/edit', [AdminController::class, 'editCategory'])->name('edit');
        Route::put('/{category}', [AdminController::class, 'updateCategory'])->name('update');
    });

    // Brands routes
    Route::get('brands', [AdminController::class, 'brands'])->name('brands.index');

    // AI Control Panel Routes
    Route::prefix('ai')->name('ai.')->group(static function (): void {
        Route::get('/', [AIControlPanelController::class, 'index'])->name('index');
        Route::post('/analyze-text', [AIControlPanelController::class, 'analyzeText'])->name('analyze-text');
        Route::post('/classify-product', [AIControlPanelController::class, 'classifyProduct'])->name('classify-product');
        Route::post('/recommendations', [AIControlPanelController::class, 'generateRecommendations'])->name('recommendations');
        Route::post('/analyze-image', [AIControlPanelController::class, 'analyzeImage'])->name('analyze-image');
        Route::get('/status', [AIControlPanelController::class, 'getStatus'])->name('status');

        // Agent Dashboard Routes
        Route::prefix('dashboard')->name('dashboard.')->group(static function (): void {
            Route::get('/', [AgentDashboardController::class, 'index'])->name('index');
            Route::get('/data', [AgentDashboardController::class, 'getDashboardData'])->name('data');
            Route::get('/stream', [AgentDashboardController::class, 'streamUpdates'])->name('stream');
            Route::get('/agents/{agentId}', [AgentDashboardController::class, 'getAgentDetails'])->name('agent.details');
            Route::get('/metrics', [AgentDashboardController::class, 'getSystemMetrics'])->name('metrics');
            Route::get('/search', [AgentDashboardController::class, 'searchAgents'])->name('search');
        });

        // Agent Management Routes
        Route::prefix('agents')->name('agents.')->group(static function (): void {
            Route::post('/{agentId}/start', [AgentManagementController::class, 'startAgent'])->name('start');
            Route::post('/{agentId}/stop', [AgentManagementController::class, 'stopAgent'])->name('stop');
            Route::post('/{agentId}/restart', [AgentManagementController::class, 'restartAgent'])->name('restart');
            Route::get('/{agentId}/config', [AgentManagementController::class, 'getConfiguration'])->name('config.get');
            Route::put('/{agentId}/config', [AgentManagementController::class, 'updateConfiguration'])->name('config.update');
            Route::post('/{agentId}/test', [AgentManagementController::class, 'testAgent'])->name('test');
            Route::get('/{agentId}/debug', [AgentManagementController::class, 'getDebugInfo'])->name('debug');
            Route::post('/{agentId}/simulate', [AgentManagementController::class, 'simulateRequest'])->name('simulate');
        });
    });

    // Scraper Routes
    Route::prefix('scraper')->name('scraper.')->group(static function (): void {
        Route::get('/', [ScraperController::class, 'index'])->name('index');
        Route::post('/start', [ScraperController::class, 'startScraping'])->name('start');
        Route::get('/logs', [ScraperController::class, 'getLogs'])->name('logs');
        Route::get('/jobs', [ScraperController::class, 'getJobs'])->name('jobs');
        Route::post('/clear-logs', [ScraperController::class, 'clearLogs'])->name('clear-logs');
        Route::post('/clear-jobs', [ScraperController::class, 'clearJobs'])->name('clear-jobs');
    });

    // System Settings - requires system.settings permission (super_admin only)
    Route::get('system-settings', [AdminController::class, 'systemSettings'])->name('system-settings');

});

// --- Brand Routes ---
// Make brands index and show public, keep other resource actions behind auth
Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('brands/{slug}', [BrandController::class, 'show'])->name('brands.show');
Route::middleware('auth')->group(static function (): void {
    Route::resource('brands', BrandController::class)->except(['index', 'show']);
});

// Secure file serving via signed URLs (private storage)
Route::get('/files/{path}', [FileController::class, 'show'])
    ->where('path', '.*')
    ->middleware(['signed'])
    ->name('files.show')
;

// --- Debug & Test Routes (Non-Production Only) ---
// WARNING: These routes are for development and debugging only. Ensure APP_DEBUG is set to false in production.
if (config('app.env') !== 'production') {
    Route::get('/public-test-ai', static function () {
        return response()->json(['success' => true, 'message' => 'Test OK']);
    })->name('public-test-ai');

    Route::middleware('auth')->get('/auth-test-ai', static function () {
        $user = auth()->user();
        return response()->json(['success' => true, 'message' => 'Auth test OK', 'user' => $user?->email ?? 'unknown']);
    })->name('auth-test-ai');

    Route::middleware('auth')->get('/ai-status-simple', static function () {
        $user = auth()->user();
        return response()->json(['success' => true, 'message' => 'AI status OK', 'user' => $user?->email ?? 'unknown']);
    });

    Route::middleware('auth')->get('/test-ai-status', static function () {
        return app(AIControlPanelController::class)->getStatus();
    })->name('test-ai-status');

    Route::get('/sentry-test', static function () {
        throw new Exception('Sentry test exception');
    });
}
