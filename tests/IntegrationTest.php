<?php
namespace BackupApp\Tests;

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testUnauthenticatedShowsAuthenticationRequired()
    {
        $php = PHP_BINARY;
        $index = realpath(__DIR__ . '/../public/index.php');

        $script = 'include ' . var_export($index, true) . ';';
        $cmd = $php . ' -r ' . escapeshellarg($script);
        exec($cmd, $output, $exitCode);
        $out = implode("\n", $output);

        $this->assertStringContainsString('Authentication required.', $out);
    }

    public function testAuthenticatedRequestShowsForm()
    {
        $php = PHP_BINARY;
        $index = realpath(__DIR__ . '/../public/index.php');

        // read credentials from .env if present, else fallback
        $env = @parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW) ?: [];
        $user = $env['BACKUP_USER'] ?? 'backup';
        $pass = $env['BACKUP_PASS'] ?? 'please-change-me-CHANGEME';

        $script = '$_SERVER["PHP_AUTH_USER"] = ' . var_export($user, true) . ';'
            . '$_SERVER["PHP_AUTH_PW"] = ' . var_export($pass, true) . ';'
            . 'include ' . var_export($index, true) . ';';

        $cmd = 'BACKUP_USER=' . escapeshellarg($user) . ' BACKUP_PASS=' . escapeshellarg($pass) . ' ' . $php . ' -r ' . escapeshellarg($script);
        exec($cmd, $output, $exitCode);
        $out = implode("\n", $output);

        $this->assertStringNotContainsString('Authentication required.', $out);
        $this->assertStringContainsString('<form', $out);
    }
}
