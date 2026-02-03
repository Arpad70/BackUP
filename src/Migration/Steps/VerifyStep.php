<?php
declare(strict_types=1);
namespace BackupApp\Migration\Steps;

use BackupApp\Migration\MigrationStepInterface;

/**
 * VerifyStep - Migration step to verify WordPress installation
 * 
 * Extracted from BackupController::handleMigrationStep() case 'verify'
 * Checks for presence of critical WordPress files and directories
 */
class VerifyStep implements MigrationStepInterface
{
    private const CRITICAL_FILES = [
        'wp-load.php',
        'wp-config.php',
        'index.php'
    ];

    private const CRITICAL_DIRS = [
        'wp-content',
        'wp-admin',
        'wp-includes'
    ];

    public function getName(): string
    {
        return 'verify';
    }

    public function getDescription(): string
    {
        return 'Verifying WordPress installation';
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $backupData): bool
    {
        if (empty($backupData['target_path'])) {
            throw new \InvalidArgumentException('Target path is required for verification');
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
        $errors = [];
        $checks = [];

        // Check critical files
        foreach (self::CRITICAL_FILES as $file) {
            $path = $targetPath . '/' . $file;
            $exists = file_exists($path);
            $checks[$file] = $exists;

            if (!$exists) {
                $errors[] = "Missing file: $file";
            }
        }

        // Check critical directories
        foreach (self::CRITICAL_DIRS as $dir) {
            $path = $targetPath . '/' . $dir;
            $exists = is_dir($path);
            $checks[$dir] = $exists;

            if (!$exists) {
                $errors[] = "Missing directory: $dir";
            }
        }

        if (!empty($errors)) {
            return [
                'ok' => false,
                'message' => 'Verification failed: ' . implode(', ', $errors),
                'data' => ['checks' => $checks, 'errors' => $errors]
            ];
        }

        return [
            'ok' => true,
            'message' => '✅ WordPress installation verified - all critical files and directories are present',
            'data' => ['checks' => $checks]
        ];
    }
}
