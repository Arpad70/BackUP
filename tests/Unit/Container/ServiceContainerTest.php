<?php
namespace BackupApp\Tests\Unit\Container;

use BackupApp\Container\ServiceContainer;
use BackupApp\Model\BackupModel;
use BackupApp\Service\Translator;
use PHPUnit\Framework\TestCase;

class ServiceContainerTest extends TestCase
{
    private ServiceContainer $container;
    
    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
    }
    
    /**
     * Test container is created successfully
     */
    public function testContainerCreatedSuccessfully(): void
    {
        $this->assertInstanceOf(ServiceContainer::class, $this->container);
    }
    
    /**
     * Test getTranslator returns Translator instance
     */
    public function testGetTranslatorReturnsTranslator(): void
    {
        $translator = $this->container->getTranslator('cs');
        
        $this->assertInstanceOf(Translator::class, $translator);
    }
    
    /**
     * Test getTranslator with different languages
     */
    public function testGetTranslatorWithDifferentLanguages(): void
    {
        $translatorCs = $this->container->getTranslator('cs');
        $translatorEn = $this->container->getTranslator('en');
        $translatorSk = $this->container->getTranslator('sk');
        
        $this->assertInstanceOf(Translator::class, $translatorCs);
        $this->assertInstanceOf(Translator::class, $translatorEn);
        $this->assertInstanceOf(Translator::class, $translatorSk);
    }
    
    /**
     * Test getBackupModel returns BackupModel instance
     */
    public function testGetBackupModelReturnsBackupModel(): void
    {
        $model = $this->container->getBackupModel();
        
        $this->assertInstanceOf(BackupModel::class, $model);
    }
    
    /**
     * Test getService returns translator
     */
    public function testGetServiceReturnsTranslator(): void
    {
        $translator = $this->container->get('translator');
        
        $this->assertInstanceOf(Translator::class, $translator);
    }
    
    /**
     * Test getService returns backup model
     */
    public function testGetServiceReturnsBackupModel(): void
    {
        $model = $this->container->get('backup_model');
        
        $this->assertInstanceOf(BackupModel::class, $model);
    }
    
    /**
     * Test service caching - same instance returned
     */
    public function testServiceCaching(): void
    {
        $translator1 = $this->container->getTranslator('cs');
        $translator2 = $this->container->get('translator');
        
        $this->assertSame($translator1, $translator2);
    }
    
    /**
     * Test has method returns true for registered services
     */
    public function testHasReturnsTrueForRegisteredServices(): void
    {
        $this->assertTrue($this->container->has('translator'));
        $this->assertTrue($this->container->has('backup_model'));
        $this->assertTrue($this->container->has('migration_registry'));
    }
    
    /**
     * Test has method returns false for unregistered services
     */
    public function testHasReturnsFalseForUnregisteredServices(): void
    {
        $this->assertFalse($this->container->has('nonexistent_service'));
        $this->assertFalse($this->container->has('unknown'));
    }
    
    /**
     * Test set method can register custom service
     */
    public function testSetMethodRegistersCustomService(): void
    {
        $this->container->set('custom_service', function () {
            return new \stdClass();
        });
        
        $this->assertTrue($this->container->has('custom_service'));
        $this->assertInstanceOf(\stdClass::class, $this->container->get('custom_service'));
    }
    
    /**
     * Test custom service is callable
     */
    public function testCustomServiceFactory(): void
    {
        $testValue = 'test_value';
        $this->container->set('custom', function () use ($testValue) {
            $obj = new \stdClass();
            $obj->value = $testValue;
            return $obj;
        });
        
        $service = $this->container->get('custom');
        
        if (!is_object($service) || !property_exists($service, 'value')) {
            $this->fail('Service does not have expected value property');
        }
        
        $this->assertEquals($testValue, $service->value);
    }
    
    /**
     * Test getLogDir returns valid path
     */
    public function testGetLogDirReturnsValidPath(): void
    {
        $logDir = $this->container->getLogDir();
        
        $this->assertIsString($logDir);
        $this->assertNotEmpty($logDir);
        $this->assertStringContainsString('logs', $logDir);
    }
    
    /**
     * Test getAppRoot returns valid path
     */
    public function testGetAppRootReturnsValidPath(): void
    {
        $appRoot = $this->container->getAppRoot();
        
        $this->assertIsString($appRoot);
        $this->assertNotEmpty($appRoot);
        $this->assertDirectoryExists($appRoot);
    }
    
    /**
     * Test app root is parent of logs directory
     */
    public function testAppRootIsParentOfLogs(): void
    {
        $appRoot = $this->container->getAppRoot();
        $logDir = $this->container->getLogDir();
        
        $appRootPrefix = trim($appRoot, '/');
        $this->assertTrue(
            strpos($logDir, $appRootPrefix) === 0 || strpos($logDir, $appRoot) === 0,
            "Log dir '$logDir' should start with app root '$appRoot'"
        );
    }
    
    /**
     * Test get throws exception for undefined service
     */
    public function testGetThrowsExceptionForUndefinedService(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->container->get('undefined_service');
    }
    
    /**
     * Test multiple translator instances with different language
     */
    public function testMultipleTranslatorInstances(): void
    {
        $translator1 = $this->container->getTranslator('cs');
        $translator2 = $this->container->getTranslator('en');
        
        // Both should be Translator instances
        $this->assertInstanceOf(Translator::class, $translator1);
        $this->assertInstanceOf(Translator::class, $translator2);
        
        // They might be different instances or cached differently
        // depending on implementation
    }
    
    /**
     * Test backup model is properly initialized
     */
    public function testBackupModelInitialized(): void
    {
        $model = $this->container->getBackupModel();
        
        // Test that model has expected methods
        $this->assertTrue(method_exists($model, 'environmentChecks'));
        $this->assertTrue(method_exists($model, 'zipDirectory'));
        $this->assertTrue(method_exists($model, 'dumpDatabase'));
    }
    
    /**
     * Test container manages migration registry
     */
    public function testContainerManagesMigrationRegistry(): void
    {
        $registry = $this->container->get('migration_registry');
        
        $this->assertNotNull($registry);
        $this->assertTrue(is_object($registry) && method_exists($registry, 'has'), 'Registry should have has() method');
        $this->assertTrue(is_object($registry) && method_exists($registry, 'execute'), 'Registry should have execute() method');
    }
}
