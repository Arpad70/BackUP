<?php
namespace BackupApp\Tests;

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testAppRenderFormWithoutAuth(): void
    {
        // No authentication required - app should render form
        $index = realpath(__DIR__ . '/../public/index.php');
        ob_start();
        include $index;
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('<form', $out);
    }
}
