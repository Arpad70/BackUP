<?php
namespace BackupApp\Tests;

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testAppRendersFormWithoutAuthentication()
    {
        $index = realpath(__DIR__ . '/../public/index.php');
        ob_start();
        include $index;
        $out = ob_get_clean();

        $this->assertStringContainsString('<form', $out);
    }
}
