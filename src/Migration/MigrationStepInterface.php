<?php
declare(strict_types=1);
namespace BackupApp\Migration;

/**
 * MigrationStepInterface - Strategy pattern for migration steps
 * 
 * Eliminates the need for switch statements in BackupController::handleMigrationStep()
 * Provides consistent interface for all 4 post-migration steps:
 * - ClearCachesStep
 * - SearchReplaceStep
 * - VerifyStep
 * - FixPermissionsStep
 * 
 * Benefits:
 * - Easy to add new migration steps without modifying controller
 * - Each step is independently testable
 * - Follows Strategy pattern
 * - Reduces BackupController from 461 to ~250 lines
 */
interface MigrationStepInterface
{
    /**
     * Execute the migration step
     * 
     * @param array<string,mixed> $backupData Backup/migration data
     * @return array<string,mixed> Result with 'ok', 'message', and optional 'data'
     */
    public function execute(array $backupData): array;

    /**
     * Validate if step can be executed
     * 
     * @param array<string,mixed> $backupData Backup/migration data
     * @return bool True if validation passed
     * 
     * @throws \InvalidArgumentException If validation fails with reason
     */
    public function validate(array $backupData): bool;

    /**
     * Get step name for identification
     * 
     * @return string Unique step identifier
     */
    public function getName(): string;

    /**
     * Get human-readable step description
     * 
     * @return string Description for logging/UI
     */
    public function getDescription(): string;
}
