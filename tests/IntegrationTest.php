<?php
namespace BackupApp\Tests;

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testAppRendersFormWithoutAuthentication()
    {
        $index = realpath(__DIR__ . '/../public/index.php');
        // Ensure request is treated as GET and no POST data is present
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        ob_start();
        include $index;
        $out = ob_get_clean();

        $this->assertStringContainsString('<form', $out);
    }
}
