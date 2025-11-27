<?php
    $initialCompareCount = count(session()->get('compare', []));
    $initialWishlistCount = auth()->check() ? auth()->user()->wishlist()->count() : 0;
?>
<nav class="bg-white dark:bg-gray-800 shadow" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="shrink-0 flex items-center">
                <a href="<?php echo e(route('home')); ?>" class="flex items-center space-x-2">
                    <img src="<?php echo e(asset('images/logo/coprra-logo.svg')); ?>" alt="<?php echo e(config('app.name', 'COPRRA')); ?>" class="h-10 w-auto">
                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?php echo e(config('app.name', 'COPRRA')); ?></span>
                </a>
            </div>

            <!-- Centered Navigation Links (Desktop) -->
            <div class="hidden md:flex space-x-8 absolute left-1/2 transform -translate-x-1/2">
                <a href="<?php echo e(route('home')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('home') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-home mr-2"></i> <?php echo e(__('Home')); ?>

                </a>
                <a href="<?php echo e(route('categories.index')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('categories.*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-tags mr-2"></i> <?php echo e(__('Categories')); ?>

                </a>
                <a href="<?php echo e(route('products.index')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('products.*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-box mr-2"></i> <?php echo e(__('Products')); ?>

                </a>
                <a href="<?php echo e(route('brands.index')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('brands.*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-building mr-2"></i> <?php echo e(__('Brands')); ?>

                </a>
                <a href="<?php echo e(route('account.wishlist')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('account.wishlist') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-heart mr-2"></i> <?php echo e(__('Wishlist')); ?>

                    <span
                        class="ml-2 inline-flex items-center justify-center min-w-[1.75rem] px-2 py-1 text-xs font-semibold leading-none rounded-full bg-pink-600/10 text-pink-600 dark:bg-pink-500/20 dark:text-pink-300 <?php echo e($initialWishlistCount > 0 ? '' : 'hidden opacity-0'); ?>"
                        data-wishlist-count
                        data-initial-count="<?php echo e($initialWishlistCount); ?>"
                    >
                        <?php echo e($initialWishlistCount); ?>

                    </span>
                </a>
                <a href="<?php echo e(route('compare.index')); ?>" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out <?php echo e(request()->routeIs('compare.*') ? 'text-blue-600 dark:text-blue-400 font-semibold' : ''); ?>">
                    <i class="fas fa-balance-scale mr-2"></i>
                    <span><?php echo e(__('Compare')); ?></span>
                    <span
                        class="ml-2 inline-flex items-center justify-center min-w-[1.75rem] px-2 py-1 text-xs font-semibold leading-none rounded-full bg-blue-600/10 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300 <?php echo e($initialCompareCount > 0 ? '' : 'hidden opacity-0'); ?>"
                        data-compare-count
                        data-initial-count="<?php echo e($initialCompareCount); ?>"
                    >
                        <?php echo e($initialCompareCount); ?>

                    </span>
                </a>
            </div>

            <!-- Right Side Of Navbar (Desktop) -->
            <div class="hidden md:flex items-center space-x-4">
                <!-- Consolidated Globe Icon I18n Dropdown -->
                <?php echo $__env->make('layouts.navigation-i18n-consolidated', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                    <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i> <?php echo e(__('Log in')); ?>

                    </a>
                <a href="#" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition" onclick="alert('Registration is currently disabled'); return false;">
                    <i class="fas fa-user-plus mr-2"></i> <?php echo e(__('Register')); ?>

                </a>
                <?php else: ?>
                    <div x-data="{ userMenuOpen: false }" class="relative">
                        <button @click="userMenuOpen = !userMenuOpen" class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                            <i class="fas fa-user"></i>
                            <span><?php echo e(Auth::user()->name); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div x-show="userMenuOpen" @click.away="userMenuOpen = false" x-transition class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-50">
                            <a href="<?php echo e(route('profile.edit')); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-cog mr-2"></i> <?php echo e(__('Profile')); ?>

                            </a>
                            <a href="<?php echo e(route('price-alerts.index')); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-bell mr-2"></i> <?php echo e(__('My Price Alerts')); ?>

                            </a>
                            <a href="<?php echo e(route('account.points')); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-coins mr-2"></i> <?php echo e(__('My Points')); ?>

                            </a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->role === 'admin' || (method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('admin'))): ?>
                            <a href="<?php echo e(route('admin.dashboard')); ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-tachometer-alt mr-2"></i> <?php echo e(__('Admin Dashboard')); ?>

                            </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <form method="POST" action="<?php echo e(route('logout')); ?>" class="block">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i> <?php echo e(__('Log Out')); ?>

                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center space-x-2">
                <button @click="searchOpen = !searchOpen" class="p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-search"></i>
                </button>
                <button @click="mobileMenuOpen = !mobileMenuOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Main menu">
                    <i :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'" class="fas fa-lg"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Search Bar -->
        <div x-show="searchOpen" x-transition class="md:hidden py-3">
            <form method="GET" action="<?php echo e(route('products.index')); ?>" class="relative">
                <input type="text" name="search" placeholder="<?php echo e(__('Search products...')); ?>" class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </form>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div x-show="mobileMenuOpen" x-transition class="md:hidden border-t border-gray-200 dark:border-gray-700">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <a href="<?php echo e(route('home')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('home') ? 'bg-gray-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400' : ''); ?>">
                <i class="fas fa-home mr-2"></i> <?php echo e(__('Home')); ?>

            </a>
            <a href="<?php echo e(route('categories.index')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('categories.*') ? 'bg-gray-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400' : ''); ?>">
                <i class="fas fa-tags mr-2"></i> <?php echo e(__('Categories')); ?>

            </a>
            <a href="<?php echo e(route('products.index')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('products.*') ? 'bg-gray-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400' : ''); ?>">
                <i class="fas fa-box mr-2"></i> <?php echo e(__('Products')); ?>

            </a>
            <a href="<?php echo e(route('brands.index')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('brands.*') ? 'bg-gray-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400' : ''); ?>">
                <i class="fas fa-building mr-2"></i> <?php echo e(__('Brands')); ?>

            </a>
            <a href="<?php echo e(route('account.wishlist')); ?>" class="flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-pink-600 dark:hover:text-pink-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('account.wishlist') ? 'bg-gray-50 dark:bg-gray-700 text-pink-600 dark:text-pink-400' : ''); ?>">
                <span>
                    <i class="fas fa-heart mr-2"></i> <?php echo e(__('Wishlist')); ?>

                </span>
                <span
                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-pink-600 rounded-full <?php echo e($initialWishlistCount > 0 ? '' : 'hidden opacity-0'); ?>"
                    data-wishlist-count
                    data-initial-count="<?php echo e($initialWishlistCount); ?>"
                >
                    <?php echo e($initialWishlistCount); ?>

                </span>
            </a>
            <a href="<?php echo e(route('compare.index')); ?>" class="flex items-center justify-between px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 <?php echo e(request()->routeIs('compare.*') ? 'bg-gray-50 dark:bg-gray-700 text-blue-600 dark:text-blue-400' : ''); ?>">
                <span>
                    <i class="fas fa-balance-scale mr-2"></i> <?php echo e(__('Compare')); ?>

                </span>
                <span
                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-blue-600 rounded-full <?php echo e($initialCompareCount > 0 ? '' : 'hidden opacity-0'); ?>"
                    data-compare-count
                    data-initial-count="<?php echo e($initialCompareCount); ?>"
                >
                    <?php echo e($initialCompareCount); ?>

                </span>
            </a>
        </div>

        <!-- Mobile i18n Options -->
        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3 space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"><?php echo e(__('Language')); ?></label>
                <form method="POST" action="<?php echo e(route('locale.language')); ?>">
                    <?php echo csrf_field(); ?>
                    <select name="language" onchange="this.form.submit()" class="w-full px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $navLanguages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $language): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($language->code); ?>" <?php echo e($language->is_current ? 'selected' : ''); ?>>
                                <?php echo e($language->native_name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </form>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($navCountries) && count($navCountries) > 0): ?>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"><?php echo e(__('Country')); ?></label>
                <form method="POST" action="<?php echo e(route('locale.country')); ?>">
                    <?php echo csrf_field(); ?>
                    <select name="country" onchange="this.form.submit()" class="w-full px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $navCountries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($country->code); ?>" <?php echo e($country->is_current ? 'selected' : ''); ?>>
                                <?php echo e($country->flag ?? ''); ?> <?php echo e($country->native_name ?? $country->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </form>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1"><?php echo e(__('Currency')); ?></label>
                <form method="POST" action="<?php echo e(route('locale.currency')); ?>">
                    <?php echo csrf_field(); ?>
                    <select name="currency" onchange="this.form.submit()" class="w-full px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $navCurrencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($currency->code); ?>" <?php echo e($currency->is_current ? 'selected' : ''); ?>>
                                <?php echo e($currency->symbol); ?> <?php echo e($currency->code); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Mobile User Menu -->
        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                <div class="space-y-2">
                    <a href="<?php echo e(route('login')); ?>" class="block w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-center">
                        <i class="fas fa-sign-in-alt mr-2"></i> <?php echo e(__('Log in')); ?>

                    </a>
                    <a href="#" class="block w-full px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition text-center" onclick="alert('Registration is currently disabled'); return false;">
                        <i class="fas fa-user-plus mr-2"></i> <?php echo e(__('Register')); ?>

                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-1">
                    <div class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                        <i class="fas fa-user mr-1"></i> <?php echo e(Auth::user()->name); ?>

                    </div>
                    <a href="<?php echo e(route('profile.edit')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-cog mr-2"></i> <?php echo e(__('Profile')); ?>

                    </a>
                    <a href="<?php echo e(route('price-alerts.index')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-bell mr-2"></i> <?php echo e(__('My Price Alerts')); ?>

                    </a>
                    <a href="<?php echo e(route('account.points')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-coins mr-2"></i> <?php echo e(__('My Points')); ?>

                    </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->role === 'admin' || (method_exists(Auth::user(), 'hasRole') && Auth::user()->hasRole('admin'))): ?>
                    <a href="<?php echo e(route('admin.dashboard')); ?>" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <i class="fas fa-tachometer-alt mr-2"></i> <?php echo e(__('Admin Dashboard')); ?>

                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <i class="fas fa-sign-out-alt mr-2"></i> <?php echo e(__('Log Out')); ?>

                        </button>
                    </form>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</nav>
<?php /**PATH /var/www/html/resources/views/layouts/navigation.blade.php ENDPATH**/ ?>