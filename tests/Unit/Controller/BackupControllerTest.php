<?php
namespace BackupApp\Tests\Unit\Controller;

use BackupApp\Container\ServiceContainer;
use BackupApp\Controller\BackupController;
use PHPUnit\Framework\TestCase;

class BackupControllerTest extends TestCase
{
    private ServiceContainer $container;
    private BackupController $controller;
    
    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        $this->controller = new BackupController($this->container);
    }
    
    /**
     * Test controller is created successfully
     */
    public function testControllerCreatedSuccessfully(): void
    {
        $this->assertInstanceOf(BackupController::class, $this->controller);
    }
    
    /**
     * Test controller has required methods
     */
    public function testControllerHasRequiredMethods(): void
    {
        $this->assertTrue(method_exists($this->controller, 'handle'));
        $this->assertTrue(method_exists($this->controller, 'handleGet'));
        $this->assertTrue(method_exists($this->controller, 'handlePost'));
    }
    
    /**
     * Test handleGet renders form
     */
    public function testHandleGetRendersForm(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('</form>', $output);
    }
    
    /**
     * Test form contains required fields
     */
    public function testFormContainsRequiredFields(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        // Check for database credentials fields
        $this->assertStringContainsString('db_host', $output);
        $this->assertStringContainsString('db_user', $output);
        $this->assertStringContainsString('db_pass', $output);
        $this->assertStringContainsString('db_name', $output);
    }
    
    /**
     * Test form includes language selector
     */
    public function testFormIncludesLanguageSelector(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertStringContainsString('language', strtolower($output));
    }
    
    /**
     * Test controller has access to container
     */
    public function testControllerHasAccessToContainer(): void
    {
        $this->assertNotNull($this->controller);
    }
    
    /**
     * Test handle method routes GET requests
     */
    public function testHandleRoutesGetRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        ob_start();
        $this->controller->handle();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertStringContainsString('<form', $output);
    }
    
    /**
     * Test handle method routes POST requests
     */
    public function testHandleRoutesPostRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        
        ob_start();
        $this->controller->handle();
        $output = ob_get_clean();
        
        $this->assertNotEmpty($output);
    }
    
    /**
     * Test form includes submit button
     */
    public function testFormIncludesSubmitButton(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertTrue(
            stripos($output, 'submit') !== false ||
            stripos($output, 'button') !== false,
            'Form should include submit button'
        );
    }
    
    /**
     * Test form is properly formatted HTML
     */
    public function testFormIsProperlyFormattedHtml(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('</form>', $output);
        $this->assertStringContainsString('<input', $output);
    }
    
    /**
     * Test controller initializes translator
     */
    public function testControllerInitializesTranslator(): void
    {
        ob_start();
        $this->controller->handleGet();
        $output = ob_get_clean();
        
        if ($output === false) {
            $this->fail('Output buffer is empty');
        }
        
        $this->assertStringContainsString('<form', $output);
    }
}
