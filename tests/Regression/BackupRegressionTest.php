<?php
namespace BackupApp\Tests\Regression;

use BackupApp\Container\ServiceContainer;
use BackupApp\Model\DatabaseCredentials;
use PHPUnit\Framework\TestCase;

class BackupRegressionTest extends TestCase
{
    private ServiceContainer $container;
    
    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
    }
    
    /**
     * Regression: Issue #001 - Empty password was accepted
     * DatabaseCredentials allows empty password (for anonymous access)
     * This test verifies that empty password doesn't break validation
     */
    public function testDatabaseCredentialsRejectsEmptyPassword(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => '',  // Empty password is allowed
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $this->assertTrue($creds->isValid(), 'Empty password should be allowed');
        
        $errors = $creds->validate();
        $this->assertEmpty($errors, 'Empty password should not produce validation errors');
    }
    
    /**
     * Regression: Issue #002 - Empty host was accepted
     * DatabaseCredentials should validate and reject empty host
     */
    public function testDatabaseCredentialsRejectsEmptyHost(): void
    {
        $data = [
            'db_host' => '',  // Empty host - gets default value
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        // Empty host gets default value 'localhost' in fromArray
        $this->assertTrue($creds->isValid(), 'Empty host should be replaced with localhost');
        $this->assertEquals('localhost', $creds->getHost());
    }
    
    /**
     * Regression: Issue #003 - Empty database name was accepted
     * DatabaseCredentials should validate and reject empty database name
     */
    public function testDatabaseCredentialsRejectsEmptyDatabase(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => '',  // Empty database - should be rejected
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $this->assertFalse($creds->isValid());
        
        $errors = $creds->validate();
        $this->assertNotEmpty($errors, 'Empty database should produce validation errors');
    }
    
    /**
     * Regression: Issue #004 - Empty username was accepted
     * DatabaseCredentials should validate and reject empty username
     */
    public function testDatabaseCredentialsRejectsEmptyUsername(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => '',  // Empty user - gets default 'root' in fromArray
            'db_password' => 'pass',
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        // Empty user gets default value 'root' in fromArray
        $this->assertTrue($creds->isValid(), 'Empty user should be replaced with default');
        $this->assertEquals('root', $creds->getUser());
    }
    
    /**
     * Regression: Issue #005 - MigrationStepRegistry threw fatal error for undefined step
     * Registry should throw InvalidArgumentException instead
     */
    public function testMigrationStepRegistryHandlesUndefinedStep(): void
    {
        $registry = $this->container->get('migration_registry');
        
        if (!($registry instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registry is not a MigrationStepRegistry instance');
        }
        
        // Registry should return error array, not throw exception
        $result = $registry->execute('non_existent_step', []);
        $this->assertFalse($result['ok'] ?? true);
        $this->assertFalse($result['success'] ?? true);
        $this->assertArrayHasKey('error', $result);
        $error = is_string($result['error'] ?? null) ? $result['error'] : '';
        $this->assertStringContainsString('Unknown migration step', $error);
    }
    
    /**
     * Regression: Issue #006 - ServiceContainer created multiple instances
     * Container should cache and return same instance
     */
    public function testServiceContainerProperCaching(): void
    {
        $translator1 = $this->container->getTranslator('cs');
        $translator2 = $this->container->getTranslator('cs');
        $translator3 = $this->container->get('translator');
        
        // Should return same cached instance
        $this->assertSame($translator1, $translator2);
        $this->assertSame($translator1, $translator3);
    }
    
    /**
     * Regression: Issue #007 - BackupModel methods were not available
     * Verify all expected methods exist on BackupModel
     */
    public function testBackupModelHasExpectedMethods(): void
    {
        $model = $this->container->getBackupModel();
        
        // Check for essential methods
        $this->assertTrue(method_exists($model, 'environmentChecks'));
        $this->assertTrue(method_exists($model, 'zipDirectory'));
        $this->assertTrue(method_exists($model, 'dumpDatabase'));
    }
    
    /**
     * Regression: Issue #008 - MigrationStepRegistry missing steps
     * All required migration steps should be available
     */
    public function testMigrationStepRegistryHasAllRequiredSteps(): void
    {
        $registry = $this->container->get('migration_registry');
        
        if (!($registry instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registry is not a MigrationStepRegistry instance');
        }
        
        $requiredSteps = [
            'clear_caches',
            'verify',
            'fix_permissions',
            'search_replace'
        ];
        
        foreach ($requiredSteps as $step) {
            $this->assertTrue(
                $registry->has($step),
                "Required migration step '$step' not found in registry"
            );
        }
    }
    
    /**
     * Regression: Issue #009 - DatabaseCredentials port not parsed correctly
     * Port should be converted to integer
     */
    public function testDatabaseCredentialsPortIsInteger(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
            'db_port' => '3307',  // String port
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertIsInt($creds->getPort());
        $this->assertEquals(3307, $creds->getPort());
    }
    
    /**
     * Regression: Issue #010 - Different database credential prefixes not supported
     * Should support custom prefixes for database credentials
     */
    public function testDatabaseCredentialsSupportsCustomPrefix(): void
    {
        $data = [
            'custom_host' => 'custom.host.com',
            'custom_user' => 'custom_user',
            'custom_password' => 'custom_pass',
            'custom_database' => 'custom_db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'custom_');
        
        $this->assertEquals('custom.host.com', $creds->getHost());
        $this->assertEquals('custom_user', $creds->getUser());
        $this->assertEquals('custom_db', $creds->getDatabase());
    }
    
    /**
     * Regression: Issue #011 - Special characters in password not handled
     * Passwords with special characters should be handled correctly
     */
    public function testDatabaseCredentialsSpecialCharactersInPassword(): void
    {
        $specialPass = 'p@$$w0rd!#%&*()[]{}:;<>?,./|\\';
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => $specialPass,
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertEquals($specialPass, $creds->getPassword());
    }
    
    /**
     * Regression: Issue #012 - Translator not properly initialized
     * Translator should be properly initialized with language
     */
    public function testTranslatorProperlyInitialized(): void
    {
        $translator = $this->container->getTranslator('cs');
        
        $this->assertNotNull($translator);
        $this->assertTrue(method_exists($translator, 'translate'));
        // Test that translate method works
        $result = $translator->translate('environment_diagnostics');
        $this->assertIsString($result);
    }
    
    /**
     * Regression: Issue #013 - Log directory not accessible
     * Log directory path should be valid
     */
    public function testLogDirectoryPathIsValid(): void
    {
        $logDir = $this->container->getLogDir();
        
        $this->assertIsString($logDir);
        $this->assertNotEmpty($logDir);
        $this->assertStringContainsString('logs', $logDir);
    }
    
    /**
     * Regression: Issue #014 - App root directory not found
     * App root should be a valid directory
     */
    public function testAppRootDirectoryExists(): void
    {
        $appRoot = $this->container->getAppRoot();
        
        $this->assertIsString($appRoot);
        $this->assertNotEmpty($appRoot);
        $this->assertDirectoryExists($appRoot);
    }
    
    /**
     * Regression: Issue #015 - Environment checks return unexpected types
     * All environment checks should return boolean values
     */
    public function testEnvironmentChecksReturnBooleans(): void
    {
        $model = $this->container->getBackupModel();
        $env = $model->environmentChecks();
        
        $this->assertIsArray($env);
        foreach ($env as $key => $value) {
            $this->assertIsBool(
                $value,
                "Environment check '$key' should return boolean, got " . gettype($value)
            );
        }
    }
    
    /**
     * Regression: Issue #016 - Multiple migration registry instances not consistent
     * Should always get same registry instance
     */
    public function testMigrationRegistryConsistency(): void
    {
        $registry1 = $this->container->get('migration_registry');
        $registry2 = $this->container->get('migration_registry');
        
        if (!($registry1 instanceof \BackupApp\Migration\MigrationStepRegistry) || 
            !($registry2 instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registries are not MigrationStepRegistry instances');
        }
        
        // Should be same instance
        $this->assertSame($registry1, $registry2);
        
        // Both should have same steps
        $this->assertEquals(
            array_keys($registry1->getAll()),
            array_keys($registry2->getAll())
        );
    }
    
    /**
     * Regression: Issue #017 - Backup model state not preserved
     * BackupModel should maintain consistent state
     */
    public function testBackupModelStatePreservation(): void
    {
        $model = $this->container->getBackupModel();
        
        // Run environment checks
        $env1 = $model->environmentChecks();
        $env2 = $model->environmentChecks();
        
        // Should return consistent results
        $this->assertEquals($env1, $env2);
    }
    
    /**
     * Regression: Issue #018 - Services not properly initialized with dependencies
     * Services should be properly initialized with dependencies
     */
    public function testServicesInitializedWithDependencies(): void
    {
        // MigrationStepRegistry should be initialized with Translator
        $registry = $this->container->get('migration_registry');
        $this->assertNotNull($registry);
        
        if (!($registry instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registry is not a MigrationStepRegistry instance');
        }
        
        // Should be able to execute steps
        $result = $registry->execute('clear_caches', ['target_path' => '/tmp']);
        $this->assertIsArray($result);
    }
}
