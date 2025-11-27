<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Environment Checker Service.
 *
 * This class is responsible for checking the server environment
 * and ensuring all requirements are met for the application.
 */
class EnvironmentChecker
{
    private const COLOR_RED = "\033[31m";

    private const COLOR_GREEN = "\033[32m";

    private const COLOR_YELLOW = "\033[33m";

    private const COLOR_BLUE = "\033[34m";

    private const COLOR_RESET = "\033[0m";

    /** @var array<string> */
    private array $errors = [];

    /** @var array<string> */
    private array $warnings = [];

    /**
     * Run all environment checks.
     */
    public function run(): void
    {
        $this->printHeader();

        $this->checkPhpVersion();
        $this->checkRequiredExtensions();
        $this->checkRecommendedExtensions();
        $this->checkPhpConfiguration();
        $this->checkDirectoryPermissions();
        $this->checkEnvironmentFile();
        $this->checkComposerDependencies();
        $this->checkDatabaseConnection();
        $this->checkCacheConfiguration();
        $this->checkQueueConfiguration();

        $this->printSummary();
    }

    /**
     * Print header.
     */
    private function printHeader(): void
    {
        $this->printInfo('=========================================');
        $this->printInfo('Environment Configuration Check');
        $this->printInfo('=========================================');
        echo \PHP_EOL;
    }

    /**
     * Print success message.
     */
    private function printSuccess(string $message): void
    {
        echo self::COLOR_GREEN."✓ {$message}".self::COLOR_RESET.\PHP_EOL;
    }

    /**
     * Print error message.
     */
    private function printError(string $message): void
    {
        echo self::COLOR_RED."✗ {$message}".self::COLOR_RESET.\PHP_EOL;
        $this->errors[] = $message;
    }

    /**
     * Print warning message.
     */
    private function printWarning(string $message): void
    {
        echo self::COLOR_YELLOW."⚠ {$message}".self::COLOR_RESET.\PHP_EOL;
        $this->warnings[] = $message;
    }

    /**
     * Print info message.
     */
    private function printInfo(string $message): void
    {
        echo self::COLOR_BLUE.$message.self::COLOR_RESET.\PHP_EOL;
    }

    /**
     * Check PHP version.
     */
    private function checkPhpVersion(): void
    {
        $this->printInfo('Checking PHP version...');
        $phpVersion = \PHP_VERSION;
        if (version_compare($phpVersion, '8.1.0', '<')) {
            $this->printError("PHP version {$phpVersion} is too old. Minimum required: 8.1.0");
        } else {
            $this->printSuccess("PHP version {$phpVersion} is compatible");
        }
        echo \PHP_EOL;
    }

    /**
     * Check required PHP extensions.
     */
    private function checkRequiredExtensions(): void
    {
        $this->printInfo('Checking required PHP extensions...');
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'json', 'bcmath', 'ctype', 'fileinfo', 'intl', 'gd'];

        foreach ($requiredExtensions as $extension) {
            if (\extension_loaded($extension)) {
                $this->printSuccess("Extension '{$extension}' is loaded");
            } else {
                $this->printError("Extension '{$extension}' is not loaded");
            }
        }
        echo \PHP_EOL;
    }

    /**
     * Check recommended PHP extensions.
     */
    private function checkRecommendedExtensions(): void
    {
        $this->printInfo('Checking recommended PHP extensions...');
        $recommendedExtensions = ['redis', 'memcached', 'gd', 'imagick', 'zip', 'curl', 'intl'];

        foreach ($recommendedExtensions as $extension) {
            if (\extension_loaded($extension)) {
                $this->printSuccess("Extension '{$extension}' is loaded");
            } else {
                $this->printWarning("Extension '{$extension}' is not loaded (optional)");
            }
        }
        echo \PHP_EOL;
    }

    /**
     * Check PHP configuration.
     */
    private function checkPhpConfiguration(): void
    {
        $this->printInfo('Checking PHP configuration...');
        $this->checkPhpConfigSetting('memory_limit', '256M', '>=');
        $this->checkPhpConfigSetting('max_execution_time', '300', '>=');
        $this->checkPhpConfigSetting('upload_max_filesize', '10M', '>=');
        $this->checkPhpConfigSetting('post_max_size', '10M', '>=');
        $this->checkPhpConfigSetting('max_input_vars', '3000', '>=');
        echo \PHP_EOL;
    }

    /**
     * Check a specific PHP configuration setting.
     */
    private function checkPhpConfigSetting(string $setting, string $requiredValue, string $operator): void
    {
        $currentValue = \ini_get($setting);
        $currentBytes = $this->returnBytes($currentValue);
        $requiredBytes = $this->returnBytes($requiredValue);

        $comparison = match ($operator) {
            '>=' => $currentBytes >= $requiredBytes,
            '>' => $currentBytes > $requiredBytes,
            '<=' => $currentBytes <= $requiredBytes,
            '<' => $currentBytes < $requiredBytes,
            default => $currentValue === $requiredValue,
        };

        if ($comparison) {
            $this->printSuccess("{$setting} is set to {$currentValue} (required: {$operator} {$requiredValue})");
        } else {
            $this->printError("{$setting} is set to {$currentValue} (required: {$operator} {$requiredValue})");
        }
    }

    /**
     * Convert PHP size format to bytes.
     */
    private function returnBytes(string $val): int
    {
        $trimmedVal = trim($val);
        $last = strtolower($trimmedVal[\strlen($trimmedVal) - 1]);
        $numericVal = (int) $trimmedVal;

        return match ($last) {
            'g' => $numericVal * 1024 * 1024 * 1024,
            'm' => $numericVal * 1024 * 1024,
            'k' => $numericVal * 1024,
            default => $numericVal,
        };
    }

    /**
     * Check directory permissions.
     */
    private function checkDirectoryPermissions(): void
    {
        $this->printInfo('Checking directory permissions...');
        $directories = ['storage', 'bootstrap/cache', 'storage/logs', 'storage/app', 'storage/framework', 'public/build', 'public/build/assets'];

        foreach ($directories as $directory) {
            $path = base_path($directory);
            if (is_dir($path) && is_writable($path)) {
                $this->printSuccess("Directory '{$directory}' is writable");
            } else {
                $this->printError("Directory '{$directory}' is not writable");
            }
        }
        echo \PHP_EOL;
    }

    /**
     * Check environment file.
     */
    private function checkEnvironmentFile(): void
    {
        $this->printInfo('Checking environment file...');
        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            $this->printSuccess('Environment file exists');
            $this->checkEnvironmentSettings();
        } else {
            $this->printError('Environment file does not exist');
        }
        echo \PHP_EOL;
    }

    /**
     * Check environment settings.
     */
    private function checkEnvironmentSettings(): void
    {
        $requiredEnvVars = ['APP_KEY', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];

        foreach ($requiredEnvVars as $var) {
            // This is a diagnostic function checking raw environment variables, not using config values
            // @phpstan-ignore-next-line larastan.noEnvCallsOutsideOfConfig
            if (null !== env($var)) {
                $this->printSuccess("Environment variable '{$var}' is set");
            } else {
                $this->printError("Environment variable '{$var}' is not set");
            }
        }
    }

    /**
     * Check composer dependencies.
     */
    private function checkComposerDependencies(): void
    {
        $this->printInfo('Checking composer dependencies...');
        $lockFile = base_path('composer.lock');

        if (file_exists($lockFile)) {
            $this->printSuccess('Composer lock file exists');
            $this->checkComposerAutoload();
        } else {
            $this->printWarning('Composer lock file does not exist');
        }
        echo \PHP_EOL;
    }

    /**
     * Check composer autoload.
     */
    private function checkComposerAutoload(): void
    {
        $autoloadPath = base_path('vendor/autoload.php');
        if (file_exists($autoloadPath)) {
            $this->printSuccess('Composer autoload file exists');
        } else {
            $this->printError('Composer autoload file does not exist');
        }
    }

    /**
     * Check database connection.
     */
    private function checkDatabaseConnection(): void
    {
        $this->printInfo('Checking database connection...');

        try {
            $dsn = \sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                config('database.connections.mysql.host', '127.0.0.1'),
                config('database.connections.mysql.port', '3306'),
                config('database.connections.mysql.database', 'forge')
            );

            new \PDO($dsn, config('database.connections.mysql.username'), config('database.connections.mysql.password'));
            $this->printSuccess('Database connection successful');
        } catch (\PDOException $e) {
            $this->printError('Database connection failed: '.$e->getMessage());
        }
        echo \PHP_EOL;
    }

    /**
     * Check cache configuration.
     */
    private function checkCacheConfiguration(): void
    {
        $this->printInfo('Checking cache configuration...');
        $cacheDriver = config('cache.default', 'file');

        if ('redis' === $cacheDriver) {
            $this->checkRedisConnection();
        } elseif ('memcached' === $cacheDriver) {
            $this->checkMemcachedConnection();
        } else {
            $this->printSuccess('Using file cache driver');
            $this->checkFileCacheDirectory();
        }
        echo \PHP_EOL;
    }

    /**
     * Check Redis connection.
     */
    private function checkRedisConnection(): void
    {
        if (\extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                $redis->connect((string) config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', '6379'));
                $this->printSuccess('Redis connection successful');
            } catch (\Exception $e) {
                $this->printError('Redis connection failed: '.$e->getMessage());
            }
        } else {
            $this->printError('Redis extension is not loaded');
        }
    }

    /**
     * Check Memcached connection.
     */
    private function checkMemcachedConnection(): void
    {
        if (\extension_loaded('memcached')) {
            try {
                $memcached = new \Memcached();
                $memcached->addServer((string) config('cache.stores.memcached.servers.0.host', '127.0.0.1'), (int) config('cache.stores.memcached.servers.0.port', '11211'));
                $this->printSuccess('Memcached connection successful');
            } catch (\Exception $e) {
                $this->printError('Memcached connection failed: '.$e->getMessage());
            }
        } else {
            $this->printError('Memcached extension is not loaded');
        }
    }

    /**
     * Check file cache directory.
     */
    private function checkFileCacheDirectory(): void
    {
        $cachePath = storage_path('framework/cache');
        if (is_dir($cachePath) && is_writable($cachePath)) {
            $this->printSuccess('Cache directory is writable');
        } else {
            $this->printError('Cache directory is not writable');
        }
    }

    /**
     * Check queue configuration.
     */
    private function checkQueueConfiguration(): void
    {
        $this->printInfo('Checking queue configuration...');
        $queueDriver = config('queue.default', 'sync');

        if ('redis' === $queueDriver) {
            $this->checkRedisQueueConnection();
        } elseif ('database' === $queueDriver) {
            $this->printSuccess('Using database queue driver');
        } else {
            $this->printWarning('Using sync queue driver (not recommended for production)');
        }
        echo \PHP_EOL;
    }

    /**
     * Check Redis queue connection.
     */
    private function checkRedisQueueConnection(): void
    {
        if (\extension_loaded('redis')) {
            try {
                $redis = new \Redis();
                $redis->connect((string) config('database.redis.default.host', '127.0.0.1'), (int) config('database.redis.default.port', '6379'));
                $this->printSuccess('Redis queue connection successful');
            } catch (\Exception $e) {
                $this->printError('Redis queue connection failed: '.$e->getMessage());
            }
        } else {
            $this->printError('Redis extension is not loaded');
        }
    }

    /**
     * Print summary.
     */
    private function printSummary(): void
    {
        $this->printInfo('=========================================');
        $this->printInfo('Environment Check Summary');
        $this->printInfo('=========================================');

        if ([] === $this->errors && [] === $this->warnings) {
            $this->printSuccess('All checks passed! Environment is ready.');
        } else {
            if ([] !== $this->errors) {
                echo \PHP_EOL;
                $this->printInfo('Errors ('.\count($this->errors).'):');
                foreach ($this->errors as $error) {
                    $this->printError($error);
                }
            }

            if ([] !== $this->warnings) {
                echo \PHP_EOL;
                $this->printInfo('Warnings ('.\count($this->warnings).'):');
                foreach ($this->warnings as $warning) {
                    $this->printWarning($warning);
                }
            }
        }

        echo \PHP_EOL;
        $this->printInfo('=========================================');
    }
}
