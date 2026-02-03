<?php
declare(strict_types=1);
namespace BackupApp\Migration\Steps;

use BackupApp\Migration\MigrationStepInterface;

/**
 * ClearCachesStep - Migration step to clear WordPress caches
 * 
 * Extracted from BackupController::handleMigrationStep() case 'clear_caches'
 * Reduces controller complexity and makes it independently testable
 * 
 * Clears:
 * - WordPress default cache directory
 * - W3 Total Cache
 * - WP Super Cache
 * - Autoptimize
 * - Plugin-specific caches
 */
class ClearCachesStep implements MigrationStepInterface
{
    private const CACHE_PATHS = [
        'wp-content/cache',
        'wp-content/plugins/*/cache',
        'wp-content/plugins/w3-total-cache',
        'wp-content/plugins/wp-super-cache',
        'wp-content/plugins/autoptimize',
    ];

    public function getName(): string
    {
        return 'clear_caches';
    }

    public function getDescription(): string
    {
        return 'Clearing WordPress caches (W3TC, WP Super Cache, Autoptimize)';
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $backupData): bool
    {
        if (empty($backupData['target_path'])) {
            throw new \InvalidArgumentException('Target path is required for cache clearing');
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
        $cleared = 0;

        foreach (self::CACHE_PATHS as $pattern) {
            $cleared += $this->clearCachePath($targetPath, $pattern);
        }

        return [
            'ok' => true,
            'message' => sprintf('🗑️ Cache cleared - removed %d files', $cleared),
            'data' => ['files_removed' => $cleared]
        ];
    }

    /**
     * Clear cache files in a specific path pattern
     */
    private function clearCachePath(string $targetPath, string $pattern): int
    {
        $fullPattern = $targetPath . '/' . $pattern;
        $files = @glob($fullPattern, GLOB_NOSORT) ?: [];
        $cleared = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    $cleared++;
                }
            }
        }

        return $cleared;
    }
}
