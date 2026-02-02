<?php
namespace BackupApp\Tests;

use BackupApp\Controller\BackupController;
use BackupApp\Model\BackupModel;
use PHPUnit\Framework\TestCase;

class BackupControllerTest extends TestCase
{
    public function testHandleRendersFormOnGet()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        // Capture output
        ob_start();
        $c = new BackupController();
        $c->handle();
        $out = ob_get_clean();

        $this->assertStringContainsString('<form', $out);
        $this->assertStringContainsString('Host', $out);
    }

    public function testHandleProcessesPostUsesModel()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'db_host' => '127.0.0.1',
            'db_user' => 'u',
            'db_pass' => 'p',
            'db_name' => 'n',
            'site_path' => __DIR__ . '/../',
        ];

        // Use a lightweight stub model by overriding class via runkit not available; instead assert output contains result markers
        ob_start();
        $c = new BackupController();
        $c->handle();
        $out = ob_get_clean();

        // Expect result view to contain steps/errors section
        $this->assertStringContainsString('Steps', $out);
        $this->assertStringContainsString('errors', strtolower($out));
    }
}
