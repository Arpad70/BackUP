<?php
namespace BackupApp\Tests\Integration;

use BackupApp\Container\ServiceContainer;
use BackupApp\Model\DatabaseCredentials;
use PHPUnit\Framework\TestCase;

class BackupWorkflowIntegrationTest extends TestCase
{
    private ServiceContainer $container;
    private string $tempDir;
    
    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        $this->tempDir = sys_get_temp_dir() . '/backup_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }
    
    protected function tearDown(): void
    {
        // Cleanup temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            if ($files === false) {
                return;
            }
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        $this->removeDirectory($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Test environment checks complete successfully
     */
    public function testEnvironmentCheckCompletes(): void
    {
        $model = $this->container->getBackupModel();
        $env = $model->environmentChecks();
        
        $this->assertIsArray($env);
        $this->assertArrayHasKey('mysqldump', $env);
        $this->assertArrayHasKey('zip_ext', $env);
        $this->assertArrayHasKey('phpseclib', $env);
    }
    
    /**
     * Test environment checks return boolean values
     */
    public function testEnvironmentChecksReturnBooleans(): void
    {
        $model = $this->container->getBackupModel();
        $env = $model->environmentChecks();
        
        foreach ($env as $key => $value) {
            $this->assertIsBool($value, "Environment check '$key' should return boolean");
        }
    }
    
    /**
     * Test zip directory workflow
     */
    public function testZipDirectoryWorkflow(): void
    {
        $model = $this->container->getBackupModel();
        
        // Create test directory with files
        $testDir = $this->tempDir . '/test_files';
        mkdir($testDir);
        file_put_contents($testDir . '/file1.txt', 'content1');
        file_put_contents($testDir . '/file2.txt', 'content2');
        
        $zipFile = $this->tempDir . '/backup.zip';
        
        // Create zip
        $result = $model->zipDirectory($testDir, $zipFile);
        
        if ($result) {
            $this->assertTrue(file_exists($zipFile), 'Zip file not created');
            $this->assertGreaterThan(0, filesize($zipFile), 'Zip file is empty');
        }
    }
    
    /**
     * Test database credentials validation workflow
     */
    public function testDatabaseCredentialsValidationWorkflow(): void
    {
        $data = [
            'target_db_database' => 'backup_db',
            'target_db_host' => 'localhost',
            'target_db_user' => 'backup_user',
            'target_db_password' => 'backup_pass',
            'target_db_port' => '3306',
        ];
        
        $creds = DatabaseCredentials::fromTargetArray($data);
        
        $this->assertNotNull($creds);
        $this->assertTrue($creds->isValid());
        $this->assertEquals('backup_db', $creds->getDatabase());
        $this->assertEquals('localhost', $creds->getHost());
    }
    
    /**
     * Test migration step registry integration
     */
    public function testMigrationStepRegistryIntegration(): void
    {
        $registry = $this->container->get('migration_registry');
        
        if (!($registry instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registry is not a MigrationStepRegistry instance');
        }
        
        $this->assertTrue($registry->has('clear_caches'));
        $this->assertTrue($registry->has('verify'));
        $this->assertTrue($registry->has('fix_permissions'));
        $this->assertTrue($registry->has('search_replace'));
    }
    
    /**
     * Test complete backup workflow execution
     */
    public function testCompleteBackupWorkflowExecution(): void
    {
        $model = $this->container->getBackupModel();
        $registry = $this->container->get('migration_registry');
        
        if (!($registry instanceof \BackupApp\Migration\MigrationStepRegistry)) {
            $this->fail('Registry is not a MigrationStepRegistry instance');
        }
        
        // Step 1: Environment checks
        $env = $model->environmentChecks();
        $this->assertIsArray($env);
        
        // Step 2: Execute clear caches step
        $result = $registry->execute('clear_caches', [
            'target_path' => $this->tempDir
        ]);
        $this->assertIsArray($result);
        
        // Step 3: Execute verify step
        $verifyData = [
            'target_path' => $this->tempDir,
            'target_db' => 'test',
            'target_db_host' => 'localhost',
            'target_db_user' => 'user',
            'target_db_password' => 'pass',
        ];
        $result = $registry->execute('verify', $verifyData);
        $this->assertIsArray($result);
    }
    
    /**
     * Test translator integration
     */
    public function testTranslatorIntegration(): void
    {
        $translator = $this->container->getTranslator('cs');
        
        $this->assertNotNull($translator);
        // Translator should have translation methods
        $this->assertTrue(method_exists($translator, 'translate'));
        // Test that translate method works
        $result = $translator->translate('environment_diagnostics');
        $this->assertIsString($result);
    }
    
    /**
     * Test service container caching in workflow
     */
    public function testServiceContainerCachingInWorkflow(): void
    {
        // Get services multiple times
        $model1 = $this->container->getBackupModel();
        $model2 = $this->container->getBackupModel();
        
        // Should return same instances (cached)
        $this->assertSame($model1, $model2);
    }
    
    /**
     * Test workflow with invalid database credentials
     */
    public function testWorkflowWithInvalidDatabaseCredentials(): void
    {
        $data = [
            'target_db' => '',  // Invalid
            'target_db_host' => '',  // Invalid
            'target_db_user' => '',  // Invalid
            'target_db_password' => 'pass',
        ];
        
        $creds = DatabaseCredentials::fromTargetArray($data);
        
        $this->assertFalse($creds->isValid());
        $errors = $creds->validate();
        $this->assertNotEmpty($errors);
    }
    
    /**
     * Test multiple environment checks
     */
    public function testMultipleEnvironmentChecks(): void
    {
        $model = $this->container->getBackupModel();
        
        // Run environment checks multiple times
        $env1 = $model->environmentChecks();
        $env2 = $model->environmentChecks();
        
        // Should return same structure
        $this->assertEquals(array_keys($env1), array_keys($env2));
    }
    
    /**
     * Test workflow state management
     */
    public function testWorkflowStateManagement(): void
    {
        $container = new ServiceContainer();
        
        // Set custom data in container
        $container->set('workflow_state', function () {
            return [
                'step' => 'initialization',
                'progress' => 0,
            ];
        });
        
        $state = $container->get('workflow_state');
        
        if (!is_array($state)) {
            $this->fail('State is not an array');
        }
        
        $this->assertEquals('initialization', $state['step'] ?? null);
        $this->assertEquals(0, $state['progress'] ?? null);
    }
}
