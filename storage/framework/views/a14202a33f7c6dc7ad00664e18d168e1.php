
<div x-data="{ i18nOpen: false }" class="relative">
    <button
        @click="i18nOpen = !i18nOpen"
        @click.away="i18nOpen = false"
        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-md transition duration-150 ease-in-out"
        aria-label="Language, Country, and Currency Settings">
        <i class="fas fa-globe text-lg"></i>
    </button>

    <div
        x-show="i18nOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-50 border border-gray-200 dark:border-gray-700"
        style="display: none;">

        <!-- Language Selection -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                <i class="fas fa-language mr-1"></i> <?php echo e(__('Language')); ?>

            </label>
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

        <!-- Country Selection -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($navCountries) && count($navCountries) > 0): ?>
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                <i class="fas fa-flag mr-1"></i> <?php echo e(__('Country')); ?>

            </label>
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

        <!-- Currency Selection -->
        <div class="px-4 py-3">
            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                <i class="fas fa-dollar-sign mr-1"></i> <?php echo e(__('Currency')); ?>

            </label>
            <form method="POST" action="<?php echo e(route('locale.currency')); ?>">
                <?php echo csrf_field(); ?>
                <select name="currency" onchange="this.form.submit()" class="w-full px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $navCurrencies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $currency): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($currency->code); ?>" <?php echo e($currency->is_current ? 'selected' : ''); ?>>
                            <?php echo e($currency->symbol); ?> <?php echo e($currency->code); ?> - <?php echo e($currency->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </form>
        </div>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/layouts/navigation-i18n-consolidated.blade.php ENDPATH**/ ?>