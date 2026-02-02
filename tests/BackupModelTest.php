<?php
namespace BackupApp\Tests;

use BackupApp\Model\BackupModel;
use PHPUnit\Framework\TestCase;

class BackupModelTest extends TestCase
{
    protected $model;

    protected function setUp(): void
    {
        $this->model = new BackupModel();
    }

    public function testEnvironmentChecksReturnsKeys()
    {
        $checks = $this->model->environmentChecks();
        $this->assertIsArray($checks);
        $this->assertArrayHasKey('mysqldump', $checks);
        $this->assertArrayHasKey('zip_ext', $checks);
        $this->assertArrayHasKey('phpseclib', $checks);
        $this->assertArrayHasKey('ssh2_ext', $checks);
        $this->assertArrayHasKey('tmp_writable', $checks);
    }

    public function testZipDirectoryCreatesZip()
    {
        $tmp = sys_get_temp_dir() . '/backup_test_' . uniqid();
        mkdir($tmp);
        $file = $tmp . '/hello.txt';
        file_put_contents($file, "hello world");

        $zip = sys_get_temp_dir() . '/backup_test_' . uniqid() . '.zip';
        $ok = $this->model->zipDirectory($tmp, $zip);
        $this->assertTrue($ok, 'zipDirectory should return true on success');
        $this->assertFileExists($zip);

        $za = new \ZipArchive();
        $res = $za->open($zip);
        $this->assertTrue($res === true);
        $this->assertNotFalse($za->locateName('hello.txt'));
        $za->close();

        unlink($file);
        rmdir($tmp);
        unlink($zip);
    }

    public function testDumpDatabaseFailsWithInvalidCredentials()
    {
        // Expecting false because credentials/DB unlikely to exist on CI/dev
        $out = $this->model->dumpDatabase('127.0.0.1', 'no_user', 'no_pass', 'no_db', 3306, sys_get_temp_dir() . '/dump_test.sql');
        $this->assertFalse($out);
    }

    public function testSftpUploadReturnsFalseForMissingLocalFile()
    {
        $ret = $this->model->sftpUpload('/path/does/not/exist.file', '/remote/tmp', 'example.invalid', 22, 'u', 'p');
        $this->assertFalse($ret);
    }
}
