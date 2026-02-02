<?php
namespace BackupApp\Tests;

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testSuccessfulBasicAuthAllowsAccess()
    {
        $php = PHP_BINARY;
        $index = realpath(__DIR__ . '/../public/index.php');

        $script = '$_SERVER["PHP_AUTH_USER"] = "' . ($envUser = 'backup') . '";'
            . '$_SERVER["PHP_AUTH_PW"] = "' . ($envPass = 'please-change-me-CHANGEME') . '";'
            . 'include ' . var_export($index, true) . ';';

        $cmd = 'BACKUP_USER=' . escapeshellarg($envUser) . ' BACKUP_PASS=' . escapeshellarg($envPass) . ' ' . $php . ' -r ' . escapeshellarg($script);
        exec($cmd, $output, $exitCode);
        $out = implode("\n", $output);

        $this->assertStringNotContainsString('Authentication required.', $out);
        $this->assertStringContainsString('<form', $out);
    }

    public function testMissingAuthShows401Message()
    {
        $php = PHP_BINARY;
        $index = realpath(__DIR__ . '/../public/index.php');

        $script = 'include ' . var_export($index, true) . ';';
        $cmd = $php . ' -r ' . escapeshellarg($script);
        exec($cmd, $output, $exitCode);
        $out = implode("\n", $output);

        $this->assertStringContainsString('Authentication required.', $out);
    }
}
