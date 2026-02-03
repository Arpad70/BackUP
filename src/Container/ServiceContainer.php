<?php
declare(strict_types=1);
namespace BackupApp\Container;

use BackupApp\Config;
use BackupApp\Model\BackupModel;
use BackupApp\Service\Translator;
use BackupApp\Service\SearchReplaceService;
use BackupApp\Service\SftpKeyUploader;
use BackupApp\Migration\MigrationStepRegistry;

/**
 * ServiceContainer - Centralized service instantiation and dependency injection
 * 
 * Manages all application services and their dependencies, ensuring:
 * - Single point of service creation
 * - Proper dependency injection
 * - Consistent initialization across application
 * - Easy testing through mock substitution
 */
class ServiceContainer
{
    /** @var array<string, mixed> Cached service instances */
    private array $services = [];
    
    /** @var array<string, callable> Service factories */
    private array $factories = [];
    
    /** @var string Application root directory */
    private string $appRoot;
    
    /** @var string Log directory path */
    private string $logDir;
    
    /**
     * @param string|null $appRoot Override application root path (for testing)
     */
    public function __construct(?string $appRoot = null)
    {
        $this->appRoot = $appRoot ?? dirname(__DIR__, 2);
        $this->logDir = $this->appRoot . '/logs';
        
        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
        
        $this->registerFactories();
    }
    
    /**
     * Register all service factories
     */
    private function registerFactories(): void
    {
        // Translator factory
        $this->factories['translator'] = function (string $lang = 'cs'): Translator {
            return new Translator($lang, [
                'fallback' => 'cs',
                'path' => $this->appRoot . '/lang'
            ]);
        };
        
        // Config factory
        $this->factories['config'] = function (): array {
            return Config::loadWordPressConfig();
        };
        
        // BackupModel factory
        $this->factories['backup_model'] = function (?SftpKeyUploader $uploader = null): BackupModel {
            $translator = $this->get('translator');
            return new BackupModel(null, $uploader, $translator instanceof Translator ? $translator : null);
        };
        
        // SearchReplaceService factory
        $this->factories['search_replace'] = function (): SearchReplaceService {
            $translator = $this->get('translator');
            return new SearchReplaceService($translator instanceof Translator ? $translator : new Translator());
        };
        
        // MigrationStepRegistry factory
        $this->factories['migration_registry'] = function (): MigrationStepRegistry {
            $translator = $this->get('translator');
            return new MigrationStepRegistry($translator instanceof Translator ? $translator : new Translator());
        };
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $name Service name (translator, backup_model, search_replace, migration_registry, config)
     * @param mixed ...$args Additional arguments to pass to factory
     * @return mixed Service instance
     * @throws \InvalidArgumentException If service not registered
     */
    public function get(string $name, ...$args): mixed
    {
        // Return cached service if available
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        
        // Check if factory exists
        if (!isset($this->factories[$name])) {
            throw new \InvalidArgumentException("Service not registered: {$name}");
        }
        
        // Create service via factory
        $service = $this->factories[$name](...$args);
        
        // Cache singleton services (backup_model with null uploader is singleton)
        if ($name !== 'backup_model' || (empty($args) || ($args[0] === null && count($args) === 1))) {
            $this->services[$name] = $service;
        }
        
        return $service;
    }
    
    /**
     * Register a custom service factory
     * 
     * @param string $name Service name
     * @param callable $factory Factory function
     */
    public function set(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
        // Clear cached instance if it exists
        unset($this->services[$name]);
    }
    
    /**
     * Check if service is registered
     */
    public function has(string $name): bool
    {
        return isset($this->factories[$name]);
    }
    
    /**
     * Get translator with specific language
     * 
     * Convenience method for getting translator with custom language
     */
    public function getTranslator(string $lang = 'cs'): Translator
    {
        $result = $this->get('translator', $lang);
        return $result instanceof Translator ? $result : new Translator($lang);
    }
    
    /**
     * Get backup model with optional SFTP uploader
     * 
     * Convenience method for creating BackupModel with uploader
     */
    public function getBackupModel(?SftpKeyUploader $uploader = null): BackupModel
    {
        $result = $this->get('backup_model', $uploader);
        if ($result instanceof BackupModel) {
            return $result;
        }
        throw new \RuntimeException('Failed to create BackupModel');
    }
    
    /**
     * Get application log directory
     */
    public function getLogDir(): string
    {
        return $this->logDir;
    }
    
    /**
     * Get application root directory
     */
    public function getAppRoot(): string
    {
        return $this->appRoot;
    }
}
