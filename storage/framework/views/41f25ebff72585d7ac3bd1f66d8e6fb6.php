<?php $__env->startSection('title', __('Home') . ' - ' . config('app.name')); ?>
<?php $__env->startSection('description', __('main.coprra_description')); ?>

<?php $__env->startSection('content'); ?>
<div class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Hero Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 mb-8">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                <?php echo e(__('Welcome to')); ?> <?php echo e(config('app.name', 'COPRRA')); ?>

            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-400">
                <?php echo e(__('main.coprra_description')); ?>

            </p>
        </div>

        <!-- Featured Products -->
        <?php if(!empty($featuredProducts) && $featuredProducts->isNotEmpty()): ?>
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6"><?php echo e(__('Featured Products')); ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php $__currentLoopData = $featuredProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="product-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                        <?php if($product->image): ?>
                            <img src="<?php echo e($product->image); ?>" alt="<?php echo e($product->name); ?>" class="w-full h-48 object-cover">
                        <?php else: ?>
                            <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center" aria-hidden="true">
                                <i class="fas fa-image fa-3x text-gray-400"></i>
                            </div>
                        <?php endif; ?>

                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                <a href="<?php echo e(route('products.show', $product->slug ?? $product->id)); ?>" class="hover:text-blue-600 dark:hover:text-blue-400" aria-label="<?php echo e(__('messages.view_details_for')); ?> <?php echo e($product->name); ?>">
                                    <?php echo e($product->name); ?>

                                </a>
                            </h3>
                            <?php if($product->brand): ?>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><?php echo e($product->brand->name); ?></p>
                            <?php endif; ?>
                            <?php if(!is_null($product->price)): ?>
                                <div class="text-xl font-bold text-blue-600 dark:text-blue-400 mb-3" aria-label="<?php echo e(__('messages.price')); ?>: $<?php echo e(number_format((float)$product->price, 2)); ?>">
                                    $<?php echo e(number_format((float)$product->price, 2)); ?>

                                </div>
                            <?php endif; ?>
                            <a href="<?php echo e(route('products.show', $product->slug ?? $product->id)); ?>" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-4 rounded transition" aria-label="<?php echo e(__('messages.view_details_for')); ?> <?php echo e($product->name); ?>">
                                <?php echo e(__('View Details')); ?>

                            </a>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php else: ?>
        <!-- Loading Skeletons for Featured Products -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6"><?php echo e(__('Featured Products')); ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php for($i = 0; $i < 4; $i++): ?>
                    <article class="skeleton-product-card skeleton bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                        <div class="skeleton-image skeleton"></div>
                        <div class="p-4">
                            <div class="skeleton-text medium skeleton mb-2"></div>
                            <div class="skeleton-text short skeleton mb-3"></div>
                            <div class="skeleton-button skeleton"></div>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Categories -->
        <?php if(!empty($categories) && $categories->isNotEmpty()): ?>
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6"><?php echo e(__('main.categories')); ?></h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $catName = is_array($category) ? ($category['name'] ?? '') : ($category->name ?? '');
                        $catCount = is_array($category) ? ($category['products_count'] ?? 0) : ($category->products_count ?? 0);
                        $catSlug = is_array($category) ? ($category['slug'] ?? '') : ($category->slug ?? '');
                        $catId = is_array($category) ? ($category['id'] ?? '') : ($category->id ?? '');
                    ?>
                    <a href="<?php echo e($catSlug ? route('categories.show', $catSlug) : route('categories.show', $catId)); ?>" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 hover:shadow-lg transition text-center">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-tags text-3xl text-blue-600 dark:text-blue-400 mb-2"></i>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1"><?php echo e($catName); ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo e($catCount); ?> <?php echo e(__('products')); ?></p>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Brands -->
        <?php if(!empty($brands) && $brands->isNotEmpty()): ?>
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6"><?php echo e(__('main.brands')); ?></h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php $__currentLoopData = $brands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $brand): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $brandName = is_array($brand) ? ($brand['name'] ?? '') : ($brand->name ?? '');
                        $brandCount = is_array($brand) ? ($brand['products_count'] ?? 0) : ($brand->products_count ?? 0);
                        $brandSlug = is_array($brand) ? ($brand['slug'] ?? '') : ($brand->slug ?? '');
                        $brandId = is_array($brand) ? ($brand['id'] ?? '') : ($brand->id ?? '');
                    ?>
                    <a href="<?php echo e($brandSlug ? route('brands.show', $brandSlug) : route('brands.show', $brandId)); ?>" class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 hover:shadow-lg transition text-center">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-building text-3xl text-blue-600 dark:text-blue-400 mb-2"></i>
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1"><?php echo e($brandName); ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo e($brandCount); ?> <?php echo e(__('products')); ?></p>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Call to Action -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-8 text-center text-white">
            <h2 class="text-3xl font-bold mb-4"><?php echo e(__('messages.start_comparing_prices')); ?></h2>
            <p class="text-lg mb-6"><?php echo e(__('messages.find_best_deals_description')); ?></p>
            <a href="<?php echo e(route('products.index')); ?>" class="inline-block bg-white text-blue-600 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition" aria-label="<?php echo e(__('messages.browse_all_products')); ?>">
                <?php echo e(__('messages.browse_all_products')); ?>

            </a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/home.blade.php ENDPATH**/ ?>