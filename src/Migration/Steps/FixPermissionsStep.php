<?php
declare(strict_types=1);
namespace BackupApp\Migration\Steps;

use BackupApp\Migration\MigrationStepInterface;

/**
 * FixPermissionsStep - Migration step to set correct file/directory permissions
 * 
 * Extracted from BackupController::handleMigrationStep() case 'fix_permissions'
 * Sets standard WordPress permissions:
 * - Directories: 755 (rwxr-xr-x)
 * - Files: 644 (rw-r--r--)
 */
class FixPermissionsStep implements MigrationStepInterface
{
    private const DIR_PERMISSIONS = 0755;
    private const FILE_PERMISSIONS = 0644;
    private const WRITABLE_DIR_PERMISSIONS = 0755;

    private const WRITABLE_DIRS = [
        'wp-content',
        'wp-content/plugins',
        'wp-content/uploads',
        'wp-content/themes'
    ];

    public function getName(): string
    {
        return 'fix_permissions';
    }

    public function getDescription(): string
    {
        return 'Setting correct file and directory permissions';
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $backupData): bool
    {
        if (empty($backupData['target_path'])) {
            throw new \InvalidArgumentException('Target path is required for permission fixing');
        }

        $targetPath = $backupData['target_path'];
        if (!is_string($targetPath) || !is_dir($targetPath)) {
            throw new \InvalidArgumentException('Target path does not exist: ' . (is_string($targetPath) ? $targetPath : 'invalid'));
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $backupData): array
    {
        try {
            $this->validate($backupData);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ];
        }

        $targetPathRaw = $backupData['target_path'];
        $targetPath = is_string($targetPathRaw) ? rtrim($targetPathRaw, '/') : '';
        if (!$targetPath) {
            return [
                'ok' => false,
                'message' => 'Invalid target path'
            ];
        }
        $processed = 0;
        $errors = [];

        // Recursively set permissions on all files and directories
        try {
            $processed = $this->setPermissionsRecursive($targetPath);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        // Ensure writable directories have correct permissions
        foreach (self::WRITABLE_DIRS as $dir) {
            $path = $targetPath . '/' . $dir;
            if (is_dir($path)) {
                $result = @chmod($path, self::WRITABLE_DIR_PERMISSIONS);
                if ($result) {
                    $processed++;
                } else {
                    $errors[] = "Failed to chmod: $dir";
                }
            }
        }

        return [
            'ok' => empty($errors),
            'message' => sprintf(
                '🔐 Permissions set - processed %d items%s',
                $processed,
                empty($errors) ? '' : ' (with some errors)'
            ),
            'data' => [
                'items_processed' => $processed,
                'errors' => $errors
            ]
        ];
    }

    /**
     * Recursively set permissions on all files and directories
     */
    private function setPermissionsRecursive(string $targetPath): int
    {
        $processed = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $targetPath,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item instanceof \SplFileInfo && $item->isDir()) {
                    @chmod($item->getRealPath() ?: '', self::DIR_PERMISSIONS);
                } elseif ($item instanceof \SplFileInfo) {
                    @chmod($item->getRealPath() ?: '', self::FILE_PERMISSIONS);
                }
                $processed++;
            }
        } catch (\Exception $e) {
            // Silent fail if unable to iterate
        }

        return $processed;
    }
}
