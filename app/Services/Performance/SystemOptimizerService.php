<?php

declare(strict_types=1);

namespace App\Services\Performance;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Console\Kernel;

final readonly class SystemOptimizerService
{
    public function __construct(
        private OutputStyle $output,
        private Kernel $kernel
    ) {}

    public function optimizeAutoloader(): void
    {
        $this->executeOptimizationTask(
            '📦 Optimizing autoloader...',
            static function (): void {
                $returnCode = 0;
                $execOutput = [];
                exec('composer dump-autoload --optimize --no-dev 2>&1', $execOutput, $returnCode);

                if (0 !== $returnCode) {
                    $details = implode("\n", $execOutput);

                    throw new \Exception('Composer autoload optimization failed'.('' !== $details ? ":\n".$details : ''));
                }
            },
            'Autoloader optimized'
        );
    }

    public function optimizeViews(): void
    {
        $this->executeOptimizationTask(
            '👁️  Optimizing views...',
            function (): void {
                $this->kernel->call('view:cache');
            },
            'Views compiled and cached'
        );
    }

    public function optimizeRoutes(): void
    {
        $this->executeOptimizationTask(
            '🛣️  Optimizing routes...',
            function (): void {
                $this->kernel->call('route:cache');
            },
            'Routes cached'
        );
    }

    public function optimizeConfig(): void
    {
        $this->executeOptimizationTask(
            '⚙️  Optimizing configuration...',
            function (): void {
                $this->kernel->call('config:cache');
            },
            'Configuration cached'
        );
    }

    /**
     * Execute a single optimization task.
     */
    private function executeOptimizationTask(string $title, callable $task, string $successMessage): void
    {
        $this->output->info($title);

        try {
            $task();
            $this->output->writeln('  ✓ '.$successMessage);
        } catch (\Exception $e) {
            $this->output->warn('  ✗ Failed: '.$e->getMessage());
        }

        $this->output->newLine();
    }
}
