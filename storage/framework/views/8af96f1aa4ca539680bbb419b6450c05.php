<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>"
      dir="<?php echo e(in_array(app()->getLocale(), ['ar', 'ur', 'fa']) ? 'rtl' : 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <?php if(isset($seoMeta)): ?>
        <?php
            $defaultDescription = View::hasSection('description') ? View::yieldContent('description') : __('messages.coprra_description');
            $defaultKeywords = View::hasSection('keywords') ? View::yieldContent('keywords') : 'price comparison, shopping, deals, discounts, COPRRA';
            $defaultOgTitle = View::hasSection('og_title') ? View::yieldContent('og_title') : (View::hasSection('title') ? View::yieldContent('title') : config('app.name', 'COPRRA'));
            $defaultOgDescription = View::hasSection('og_description') ? View::yieldContent('og_description') : __('messages.coprra_description');
            $defaultOgImage = View::hasSection('og_image') ? View::yieldContent('og_image') : asset('images/logo/coprra-logo.svg');
            $defaultTitle = View::hasSection('title') ? View::yieldContent('title') : config('app.name', 'COPRRA');
        ?>
        <meta name="description" content="<?php echo e($seoMeta['description'] ?? $defaultDescription); ?>">
        <meta name="keywords" content="<?php echo e($seoMeta['keywords'] ?? $defaultKeywords); ?>">
        <meta name="robots" content="<?php echo e($seoMeta['robots'] ?? 'index, follow'); ?>">
        <link rel="canonical" href="<?php echo e($seoMeta['canonical'] ?? url()->current()); ?>">
        
        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo e($seoMeta['og_title'] ?? $defaultOgTitle); ?>">
        <meta property="og:description" content="<?php echo e($seoMeta['og_description'] ?? $defaultOgDescription); ?>">
        <meta property="og:type" content="<?php echo e($seoMeta['og_type'] ?? 'website'); ?>">
        <meta property="og:url" content="<?php echo e($seoMeta['og_url'] ?? url()->current()); ?>">
        <meta property="og:image" content="<?php echo e($seoMeta['og_image'] ?? $defaultOgImage); ?>">
        
        <title><?php echo e($seoMeta['title'] ?? $defaultTitle); ?></title>
    <?php else: ?>
        <meta name="description" content="<?php echo $__env->yieldContent('description', __('messages.coprra_description')); ?>">
        <meta name="keywords" content="<?php echo $__env->yieldContent('keywords', 'price comparison, shopping, deals, discounts, COPRRA'); ?>">
        <meta name="author" content="<?php echo e(config('app.name', 'COPRRA')); ?>">
        <meta name="theme-color" content="#0A1E40">
        <meta name="color-scheme" content="light dark">

        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo $__env->yieldContent('og_title', View::hasSection('title') ? View::yieldContent('title') : config('app.name', 'COPRRA')); ?>">
        <meta property="og:description" content="<?php echo $__env->yieldContent('og_description', __('messages.coprra_description')); ?>">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo e(url()->current()); ?>">
        <meta property="og:image" content="<?php echo $__env->yieldContent('og_image', asset('images/logo/coprra-logo.svg')); ?>">

        <title><?php echo $__env->yieldContent('title', config('app.name', 'COPRRA')); ?></title>
    <?php endif; ?>
    
    <meta name="author" content="<?php echo e(config('app.name', 'COPRRA')); ?>">
    <meta name="theme-color" content="#0A1E40">
    <meta name="color-scheme" content="light dark">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?php echo e(asset('images/logo/coprra-icon.svg')); ?>">
    <link rel="alternate icon" type="image/png" href="<?php echo e(asset('favicon.png')); ?>">
    
    <?php if(isset($productSchema)): ?>
    <!-- Product Schema (JSON-LD) -->
    <script type="application/ld+json">
    <?php echo json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>

    </script>
    <?php endif; ?>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo e(asset('images/logo/coprra-icon.svg')); ?>">

    <!-- Fonts: use system stack locally (remove external CDNs) -->
    <!-- External font CDNs removed to meet Local First directive -->

    <!-- Icons: Font Awesome (local) -->
    <link rel="stylesheet" href="<?php echo e(asset('vendor/fontawesome/css/all.min.css')); ?>">

    <!-- Critical CSS -->
    <?php if(\Illuminate\Support\Facades\File::exists(resource_path('css/critical.css'))): ?>
    <style><?php echo \Illuminate\Support\Facades\File::get(resource_path('css/critical.css')); ?></style>
    <?php endif; ?>

    <!-- Brand CSS -->
    <link rel="stylesheet" href="<?php echo e(asset('css/coprra-brand.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/coprra-utilities.css')); ?>">

    <!-- RTL Support for Arabic, Hebrew, Urdu, Farsi -->
    <?php if(in_array(app()->getLocale(), ['ar', 'ur', 'fa', 'he'])): ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/rtl.css')); ?>">
    <?php endif; ?>

    <!-- Additional CSS -->
    <?php echo $__env->yieldPushContent('styles'); ?>

    <!-- Alpine.js (local) - loaded globally for navigation component -->
    <script defer src="<?php echo e(asset('vendor/alpinejs/alpine.min.js')); ?>"></script>

    <!-- Scripts -->
    <?php if(app()->environment('testing')): ?>
        
    <?php else: ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php endif; ?>

    <!-- Livewire (excluded on home to avoid CSP eval warning) -->
    <?php if (! (request()->routeIs('home'))): ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    <?php endif; ?>

    <!-- Google Analytics -->
    <?php if(config('services.google_analytics.id')): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e(config('services.google_analytics.id')); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo e(config('services.google_analytics.id')); ?>');
    </script>
    <?php endif; ?>
</head>
<body class="font-sans antialiased"
      data-authenticated="<?php echo e(auth()->check() ? 'true' : 'false'); ?>"
      data-login-url="<?php echo e(route('login')); ?>"
      data-register-url="#">
    <!-- Skip Links for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <a href="#navigation" class="skip-link">Skip to navigation</a>

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <?php echo $__env->make('layouts.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <!-- Page Heading -->
        <?php if(isset($header)): ?>
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <?php echo e($header); ?>

                </div>
            </header>
        <?php endif; ?>

        <!-- Page Content -->
        <main id="main-content" role="main">
            <?php echo $__env->yieldContent('content'); ?>
        </main>

        <!-- Footer -->
        <?php echo $__env->make('layouts.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <!-- Livewire (excluded on home) -->
    <?php if (! (request()->routeIs('home'))): ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

    <?php endif; ?>

    <!-- Additional JS -->
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>