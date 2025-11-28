<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Backup\Services\BackupDatabaseService;
use App\Services\Backup\Services\BackupFileService;
use Illuminate\Support\Facades\Log;

/**
 * Refactored BackupService - delegates to specialized services.
 * Original BackupService was 541 lines - this is much cleaner.
 */
class BackupServiceRefactored
{
    public function __construct(
        private readonly BackupDatabaseService $databaseService,
        private readonly BackupFileService $fileService
    ) {
    }

    /**
     * Create full backup (database + files).
     */
    public function createFullBackup(): array
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $results = [
            'success' => true,
            'database' => false,
            'files' => false,
            'paths' => [],
            'errors' => [],
        ];

        // Backup database
        $dbBackupPath = $backupDir.'/database_'.$timestamp.'.sql';
        if ($this->databaseService->createDatabaseBackup($dbBackupPath)) {
            $results['database'] = true;
            $results['paths'][] = $dbBackupPath;
            Log::info('Database backup created', ['path' => $dbBackupPath]);
        } else {
            $results['success'] = false;
            $results['errors'][] = 'Database backup failed';
            Log::error('Database backup failed');
        }

        // Backup files
        $filesBackupPath = $backupDir.'/files_'.$timestamp.'.zip';
        if ($this->fileService->createFilesBackup($filesBackupPath)) {
            $results['files'] = true;
            $results['paths'][] = $filesBackupPath;
            Log::info('Files backup created', ['path' => $filesBackupPath]);
        } else {
            $results['success'] = false;
            $results['errors'][] = 'Files backup failed';
            Log::error('Files backup failed');
        }

        // Clean old backups (keep last 30 days)
        $this->fileService->cleanOldBackups('backups', 30);

        return $results;
    }

    /**
     * Create database-only backup.
     */
    public function createDatabaseBackup(): ?string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $dbBackupPath = $backupDir.'/database_'.$timestamp.'.sql';

        if ($this->databaseService->createDatabaseBackup($dbBackupPath)) {
            Log::info('Database backup created', ['path' => $dbBackupPath]);

            return $dbBackupPath;
        }

        Log::error('Database backup failed');

        return null;
    }

    /**
     * Create files-only backup.
     */
    public function createFilesBackup(array $directories = []): ?string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filesBackupPath = $backupDir.'/files_'.$timestamp.'.zip';

        if ($this->fileService->createFilesBackup($filesBackupPath, $directories)) {
            Log::info('Files backup created', ['path' => $filesBackupPath]);

            return $filesBackupPath;
        }

        Log::error('Files backup failed');

        return null;
    }

    /**
     * Restore database from backup.
     */
    public function restoreDatabase(string $backupPath): bool
    {
        if (! file_exists($backupPath)) {
            Log::error('Backup file not found', ['path' => $backupPath]);

            return false;
        }

        $result = $this->databaseService->restoreDatabase($backupPath);

        if ($result) {
            Log::info('Database restored successfully', ['path' => $backupPath]);
        } else {
            Log::error('Database restore failed', ['path' => $backupPath]);
        }

        return $result;
    }

    /**
     * Get backup status.
     */
    public function getBackupStatus(): array
    {
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            return [
                'last_backup' => null,
                'total_backups' => 0,
                'total_size' => 0,
            ];
        }

        $files = array_diff(scandir($backupDir), ['.', '..']);
        $totalSize = 0;
        $lastBackupTime = null;

        foreach ($files as $file) {
            $filePath = $backupDir.'/'.$file;
            $totalSize += filesize($filePath);
            $fileTime = filemtime($filePath);

            if (null === $lastBackupTime || $fileTime > $lastBackupTime) {
                $lastBackupTime = $fileTime;
            }
        }

        return [
            'last_backup' => $lastBackupTime ? date('Y-m-d H:i:s', $lastBackupTime) : null,
            'total_backups' => \count($files),
            'total_size' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Clean old backups.
     */
    public function cleanOldBackups(int $keepDays = 30): void
    {
        $this->fileService->cleanOldBackups('backups', $keepDays);
        Log::info('Old backups cleaned', ['keep_days' => $keepDays]);
    }

    /**
     * Validate backup file.
     */
    public function validateBackup(string $path): bool
    {
        return $this->fileService->validateBackup($path);
    }

    /**
     * Format bytes to human-readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            ++$i;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
