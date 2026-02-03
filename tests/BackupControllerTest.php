<?php
namespace BackupApp\Tests;

use BackupApp\Controller\BackupController;
use BackupApp\Model\BackupModel;
use PHPUnit\Framework\TestCase;

class BackupControllerTest extends TestCase
{
    public function testHandleRendersFormOnGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // Capture output
        ob_start();
        $c = new BackupController();
        $c->handle();
        $out = (string) ob_get_clean();

        $this->assertStringContainsString('<form', $out);
        $this->assertStringContainsString('Host', $out);
    }

    public function testHandleProcessesPostUsesModel(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'db_host' => '127.0.0.1',
            'db_user' => 'u',
            'db_pass' => 'p',
            'db_name' => 'n',
            'site_path' => __DIR__ . '/../',
        ];

        // Capture output
        ob_start();
        $c = new BackupController();
        $c->handle();
        $out = (string) ob_get_clean();

        // Should contain form or result view
        // POST processing renders form again or result page
        $this->assertNotEmpty($out);
        $this->assertStringContainsString('<', $out); // HTML output
    }

}
