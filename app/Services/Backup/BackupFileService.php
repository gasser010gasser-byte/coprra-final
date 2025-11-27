<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;

class BackupFileService
{
    private readonly string $backupPath;

    public function __construct(string $backupPath)
    {
        $this->backupPath = $backupPath;
    }

    /**
     * Delete backup file and log the operation.
     *
     * @param  array<string, string|int|* @method static \App\Models\Brand create(array<string, string|bool|null>  $backup
     */
    public function deleteBackupFile(array $backup, string $backupId): void
    {
        $filePath = $this->getBackupFilePath($backup);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        Log::info('Backup deleted: '.$backupId);
    }

    /**
     * Get backup file path.
     *
     * @param  array<string, string|int|* @method static \App\Models\Brand create(array<string, string|bool|null>  $backup
     */
    public function getBackupFilePath(array $backup): string
    {
        $filename = $backup['filename'] ?? '';

        return $this->backupPath.'/'.$filename;
    }

    /**
     * Check if backup file exists.
     *
     * @param  array<string, string|int|* @method static \App\Models\Brand create(array<string, string|bool|null>  $backup
     */
    public function backupFileExists(array $backup): bool
    {
        $filePath = $this->getBackupFilePath($backup);

        return file_exists($filePath);
    }

    /**
     * Get backup file size.
     *
     * @param  array<string, string|int|* @method static \App\Models\Brand create(array<string, string|bool|null>  $backup
     */
    public function getBackupFileSize(array $backup): int
    {
        $filePath = $this->getBackupFilePath($backup);

        if (! file_exists($filePath)) {
            return 0;
        }

        $fileSize = filesize($filePath);

        return false !== $fileSize ? $fileSize : 0;
    }
}
