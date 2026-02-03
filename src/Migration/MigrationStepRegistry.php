<?php
declare(strict_types=1);
namespace BackupApp\Migration;

use BackupApp\Service\Translator;

/**
 * MigrationStepRegistry - Central registry for migration steps
 * 
 * Replaces the large switch statement in BackupController::handleMigrationStep()
 * Eliminates ~100 lines of repetitive case statements
 * 
 * Usage:
 * $registry = new MigrationStepRegistry($translator);
 * $step = $registry->get('clear_caches');
 * $result = $step->execute($backupData);
 */
class MigrationStepRegistry
{
    /** @var array<string, MigrationStepInterface> */
    private array $steps = [];

    private Translator $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
        $this->registerDefaultSteps();
    }

    /**
     * Register default migration steps
     */
    private function registerDefaultSteps(): void
    {
        $this->register('clear_caches', new Steps\ClearCachesStep());
        $this->register('verify', new Steps\VerifyStep());
        $this->register('fix_permissions', new Steps\FixPermissionsStep());
        $this->register('search_replace', new Steps\SearchReplaceStep($this->translator));
    }

    /**
     * Register a migration step
     */
    public function register(string $name, MigrationStepInterface $step): void
    {
        if ($name !== $step->getName()) {
            throw new \InvalidArgumentException(
                sprintf('Step name mismatch: %s != %s', $name, $step->getName())
            );
        }

        $this->steps[$name] = $step;
    }

    /**
     * Get a migration step by name
     * 
     * @throws \LogicException If step not found
     */
    public function get(string $stepName): MigrationStepInterface
    {
        if (!isset($this->steps[$stepName])) {
            throw new \LogicException('Unknown migration step: ' . $stepName);
        }

        return $this->steps[$stepName];
    }

    /**
     * Check if a step is registered
     */
    public function has(string $stepName): bool
    {
        return isset($this->steps[$stepName]);
    }

    /**
     * Get all registered steps
     * 
     * @return array<string, MigrationStepInterface>
     */
    public function getAll(): array
    {
        return $this->steps;
    }

    /**
     * Execute a migration step
     * 
     * Returns result array with automatic error handling
     * 
     * @param string $stepName Step identifier
     * @param array<string,mixed> $backupData Backup metadata
     * @return array<string,mixed>
     */
    public function execute(string $stepName, array $backupData): array
    {
        if (!$this->has($stepName)) {
            return [
                'ok' => false,
                'success' => false,
                'error' => 'Unknown migration step: ' . $stepName,
                'output' => 'Error: Unknown migration step',
                'message' => 'Unknown migration step: ' . $stepName
            ];
        }

        try {
            $step = $this->get($stepName);

            // Validate before execution
            try {
                $step->validate($backupData);
            } catch (\InvalidArgumentException $e) {
                return [
                    'ok' => false,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'output' => 'Error: ' . $e->getMessage(),
                    'message' => $e->getMessage()
                ];
            }

            // Execute step
            $result = $step->execute($backupData);

            return [
                'ok' => ($result['ok'] ?? false),
                'success' => ($result['ok'] ?? false),
                'output' => $result['message'] ?? 'Step completed',
                'result' => $result,
                'error' => ($result['ok'] ?? false) ? null : ($result['message'] ?? 'Unknown error')
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'success' => false,
                'error' => $e->getMessage(),
                'output' => 'Error: ' . $e->getMessage(),
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get step list for UI
     * 
     * @return array<string,array<string,string>>
     */
    public function getStepsList(): array
    {
        $list = [];
        foreach ($this->steps as $name => $step) {
            $list[$name] = [
                'name' => $step->getName(),
                'description' => $step->getDescription(),
            ];
        }
        return $list;
    }
}
