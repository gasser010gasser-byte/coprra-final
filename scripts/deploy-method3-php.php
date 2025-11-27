<?php
/**
 * Method 3: Using PHP exec with SSH
 */

echo "========================================\n";
echo "Method 3: Using PHP exec with SSH\n";
echo "========================================\n\n";

$commands = [
    'cd /home/u990109832/domains/coprra.com/public_html',
    'git fetch origin',
    'git checkout feature/build-affiliate-store-foundation',
    'git pull origin feature/build-affiliate-store-foundation',
    'composer install --no-dev --optimize-autoloader',
    'php artisan config:clear',
    'php artisan config:cache',
    'php artisan route:clear',
    'php artisan route:cache',
    'php artisan view:clear',
    'php artisan view:cache',
    'echo "DEPLOYMENT_COMPLETE"'
];

$commandString = implode(' && ', $commands);
$sshCommand = "ssh -p 65002 u990109832@45.87.81.218 \"$commandString\"";

echo "Executing: $sshCommand\n\n";

exec($sshCommand, $output, $returnCode);

echo implode("\n", $output) . "\n\n";

if ($returnCode === 0) {
    echo "✅ Deployment completed successfully!\n";
} else {
    echo "❌ Deployment failed with exit code: $returnCode\n";
}

