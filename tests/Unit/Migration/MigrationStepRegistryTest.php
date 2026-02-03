<?php
namespace BackupApp\Tests\Unit\Migration;

use BackupApp\Migration\MigrationStepRegistry;
use BackupApp\Service\Translator;
use PHPUnit\Framework\TestCase;

class MigrationStepRegistryTest extends TestCase
{
    private MigrationStepRegistry $registry;
    private Translator $translator;
    
    protected function setUp(): void
    {
        $langPath = dirname(__DIR__, 3) . '/lang';
        $this->translator = new Translator('cs', [
            'fallback' => 'cs',
            'path' => $langPath
        ]);
        $this->registry = new MigrationStepRegistry($this->translator);
    }
    
    /**
     * Test registry is created successfully
     */
    public function testRegistryCreatedSuccessfully(): void
    {
        $this->assertInstanceOf(MigrationStepRegistry::class, $this->registry);
    }
    
    /**
     * Test registry has preregistered steps
     */
    public function testRegistryHasPreregisteredSteps(): void
    {
        $this->assertTrue($this->registry->has('clear_caches'));
        $this->assertTrue($this->registry->has('verify'));
        $this->assertTrue($this->registry->has('fix_permissions'));
        $this->assertTrue($this->registry->has('search_replace'));
    }
    
    /**
     * Test has returns false for unregistered steps
     */
    public function testHasReturnsFalseForUnregisteredSteps(): void
    {
        $this->assertFalse($this->registry->has('unknown_step'));
        $this->assertFalse($this->registry->has('fake_migration'));
    }
    
    /**
     * Test execute returns array for valid step
     */
    public function testExecuteReturnsArrayForValidStep(): void
    {
        $result = $this->registry->execute('clear_caches', [
            'target_path' => '/tmp'
        ]);
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test execute returns ok key
     */
    public function testExecuteReturnsOkKey(): void
    {
        $result = $this->registry->execute('clear_caches', [
            'target_path' => '/tmp'
        ]);
        
        $this->assertArrayHasKey('ok', $result);
    }
    
    /**
     * Test execute returns array for unregistered step (doesn't throw)
     */
    public function testExecuteThrowsExceptionForUnregisteredStep(): void
    {
        // execute() should NOT throw for unknown steps - just return error array
        $result = $this->registry->execute('unknown_step', []);
        
        $this->assertIsArray($result);
        $this->assertFalse($result['ok']);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }
    
    /**
     * Test getAll returns all steps
     */
    public function testGetAllReturnsAllSteps(): void
    {
        $all = $this->registry->getAll();
        
        $this->assertIsArray($all);
        $this->assertGreaterThanOrEqual(4, count($all));
    }
    
    /**
     * Test getAll returns array with step names as keys
     */
    public function testGetAllReturnsStepNamesAsKeys(): void
    {
        $all = $this->registry->getAll();
        
        $this->assertArrayHasKey('clear_caches', $all);
        $this->assertArrayHasKey('verify', $all);
        $this->assertArrayHasKey('fix_permissions', $all);
        $this->assertArrayHasKey('search_replace', $all);
    }
    
    /**
     * Test clear caches step execution
     */
    public function testClearCachesStepExecution(): void
    {
        $result = $this->registry->execute('clear_caches', [
            'target_path' => '/tmp'
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ok', $result);
    }
    
    /**
     * Test verify step execution
     */
    public function testVerifyStepExecution(): void
    {
        $result = $this->registry->execute('verify', [
            'target_path' => '/tmp',
            'target_db' => 'test',
            'target_db_host' => 'localhost',
            'target_db_user' => 'user',
            'target_db_password' => 'pass',
        ]);
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test fix permissions step execution
     */
    public function testFixPermissionsStepExecution(): void
    {
        $result = $this->registry->execute('fix_permissions', [
            'target_path' => '/tmp'
        ]);
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test search replace step execution
     */
    public function testSearchReplaceStepExecution(): void
    {
        $result = $this->registry->execute('search_replace', [
            'search' => 'old',
            'replace' => 'new',
            'target_db' => 'test',
            'target_db_host' => 'localhost',
            'target_db_user' => 'user',
            'target_db_password' => 'pass',
        ]);
        
        $this->assertIsArray($result);
    }
    
    /**
     * Test step execution with minimal parameters
     */
    public function testStepExecutionWithMinimalParameters(): void
    {
        $result = $this->registry->execute('clear_caches', []);
        
        // Should still return array (might have error messages)
        $this->assertIsArray($result);
    }
    
    /**
     * Test all steps are callable
     */
    public function testAllStepsAreCallable(): void
    {
        $steps = $this->registry->getAll();
        
        foreach ($steps as $stepName => $step) {
            $this->assertTrue($this->registry->has($stepName));
            // Verify step is callable by attempting execution
            $result = $this->registry->execute($stepName, []);
            $this->assertIsArray($result);
        }
    }
    
    /**
     * Test step names are consistent
     */
    public function testStepNamesAreConsistent(): void
    {
        $steps = $this->registry->getAll();
        $expectedSteps = [
            'clear_caches',
            'verify',
            'fix_permissions',
            'search_replace'
        ];
        
        foreach ($expectedSteps as $step) {
            $this->assertArrayHasKey($step, $steps);
        }
    }
    
    /**
     * Test registry with different language
     */
    public function testRegistryWithDifferentLanguage(): void
    {
        $langPath = dirname(__DIR__, 3) . '/lang';
        $translatorEn = new Translator('en', [
            'fallback' => 'cs',
            'path' => $langPath
        ]);
        $registryEn = new MigrationStepRegistry($translatorEn);
        
        // Should have same steps
        $this->assertTrue($registryEn->has('clear_caches'));
        $this->assertTrue($registryEn->has('verify'));
    }
}
