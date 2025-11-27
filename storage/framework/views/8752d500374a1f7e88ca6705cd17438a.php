<footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700" role="contentinfo">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Company Info -->
            <div class="col-span-1 md:col-span-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    <?php echo e(config('app.name', 'COPRRA')); ?>

                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    <?php echo e(__('main.coprra_description')); ?>

</p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4">
                    <?php echo e(__('main.quick_links')); ?>

                </h4>
                <ul class="space-y-2">
                    <li><a href="<?php echo e(route('home')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.home')); ?></a></li>
                    <li><a href="<?php echo e(route('products.index')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.products')); ?></a></li>
                    <li><a href="<?php echo e(route('categories.index')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.categories')); ?></a></li>
                    <li><a href="<?php echo e(route('brands.index')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.brands')); ?></a></li>
                    <li><a href="<?php echo e(route('stores.index')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.stores')); ?></a></li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4">
                    <?php echo e(__('main.support')); ?>

                </h4>
                <ul class="space-y-2">
                    <li><a href="<?php echo e(route('faq')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.help_center')); ?></a></li>
                    <li><a href="<?php echo e(route('contact')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.contact_us')); ?></a></li>
                    <li><a href="<?php echo e(route('privacy')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.privacy_policy')); ?></a></li>
                    <li><a href="<?php echo e(route('terms')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"><?php echo e(__('main.terms_of_service')); ?></a></li>
                    <li><a href="<?php echo e(route('about')); ?>" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">About Us</a></li>
                </ul>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    &copy; <?php echo e(date('Y')); ?> <?php echo e(config('app.name', 'COPRRA')); ?>. <?php echo e(__('main.all_rights_reserved')); ?>.
                </p>
            </div>
        </div>
    </div>
</footer>
<?php /**PATH /var/www/html/resources/views/layouts/footer.blade.php ENDPATH**/ ?>