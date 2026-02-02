<?php
declare(strict_types=1);
namespace BackupApp\Model;

use BackupApp\Service\DatabaseDumper;
use BackupApp\Service\SftpUploader;

class BackupModel
{
    protected string $tmpDir;
    private ?string $progressFile = null;
    private DatabaseDumper $dumper;
    private SftpUploader $uploader;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
        $this->dumper = new DatabaseDumper();
        $this->uploader = new SftpUploader();
    }

    private function setProgress(int $percent, string $message = '', string $step = ''): void
    {
        if (!$this->progressFile) return;
        $data = [
            'progress' => min(100, max(0, (int)$percent)),
            'message' => $message,
            'step' => $step,
            'timestamp' => time()
        ];
        @file_put_contents($this->progressFile, json_encode($data));
    }

    public function getProgressFile(): ?string
    {
        return $this->progressFile;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function runBackup(array $data): array
    {
        $response = ['steps' => [], 'errors' => []];
        $this->progressFile = sys_get_temp_dir() . '/backup_progress_' . time() . '.json';
        $this->setProgress(0, 'Initializing...');

        $env = $this->environmentChecks();
        $response['env'] = $env;
        if (! $env['mysqldump'] || ! $env['zip_ext'] || ! $env['tmp_writable']) {
            $response['errors'][] = 'Environment incomplete: missing required tools or permissions.';
            $this->setProgress(0, 'Error: ' . $response['errors'][0]);
            return $response;
        }

        $this->setProgress(10, 'Dumping database...', 'db_dump');
        $dbFile = $this->tmpDir . '/db_dump_' . time() . '.sql';
        $dbHost = $data['db_host'] ?? null;
        if (!is_string($dbHost) || $dbHost === '') {
            $dbHost = '127.0.0.1';
        }
        $dbUser = $data['db_user'] ?? null;
        if (!is_string($dbUser)) {
            $dbUser = '';
        }
        $dbPass = $data['db_pass'] ?? null;
        if (!is_string($dbPass)) {
            $dbPass = '';
        }
        $dbName = $data['db_name'] ?? null;
        if (!is_string($dbName)) {
            $dbName = '';
        }
        $dbPort = $data['db_port'] ?? null;
        if (is_int($dbPort)) {
            // ok
        } elseif (is_string($dbPort) && ctype_digit($dbPort)) {
            $dbPort = (int) $dbPort;
        } else {
            $dbPort = 3306;
        }

        $dbResult = $this->dumpDatabase(
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            $dbPort,
            $dbFile
        );

        // dumpDatabase() returns an array with keys 'ok' and 'message'
        $dbOk = $dbResult['ok'] ?? false;
        $dbMsg = $dbResult['message'] ?? '';

        $response['steps'][] = ['db_dump' => $dbFile, 'ok' => $dbOk, 'message' => $dbMsg];
        if (! $dbOk) {
            $response['errors'][] = 'Database dump failed';
            if (!empty($dbMsg)) $response['errors'][] = 'Dump error: ' . $dbMsg;
            $this->setProgress(50, 'Error: Database dump failed');
            return $response;
        }
        $this->setProgress(35, 'Database dump completed');

        $sitePath = $data['site_path'] ?? null;
        if (!is_string($sitePath) || $sitePath === '' || ! is_dir($sitePath)) {
            $response['errors'][] = 'Invalid site path';
            $this->setProgress(50, 'Error: Invalid site path');
            return $response;
        }

        $this->setProgress(40, 'Compressing site files...', 'zip');
        $zipFile = $this->tmpDir . '/site_backup_' . time() . '.zip';
        $okZip = $this->zipDirectory($sitePath, $zipFile);
        $response['steps'][] = ['site_zip' => $zipFile, 'ok' => $okZip];
        if (! $okZip) {
            $response['errors'][] = 'Site zip failed';
            $this->setProgress(50, 'Error: Site compression failed');
            return $response;
        }
        $this->setProgress(65, 'Files compressed');

        $sftpHost = $data['sftp_host'] ?? null;
        if (!is_string($sftpHost)) {
            $sftpHost = '';
        }
        $sftpPort = $data['sftp_port'] ?? null;
        if (is_int($sftpPort)) {
            // ok
        } elseif (is_string($sftpPort) && ctype_digit($sftpPort)) {
            $sftpPort = (int) $sftpPort;
        } else {
            $sftpPort = 22;
        }
        $sftpUser = $data['sftp_user'] ?? null;
        if (!is_string($sftpUser)) {
            $sftpUser = '';
        }
        $sftpPass = $data['sftp_pass'] ?? null;
        if (!is_string($sftpPass)) {
            $sftpPass = '';
        }
        $remoteDir = $data['sftp_remote'] ?? null;
        if (!is_string($remoteDir)) {
            $remoteDir = '.';
        }
        $remoteDir = rtrim($remoteDir, '/');

        $this->setProgress(70, 'Uploading database to SFTP...', 'upload_db');
        $uplDB = $this->sftpUpload($dbFile, $remoteDir . '/' . basename($dbFile), $sftpHost, $sftpPort, $sftpUser, $sftpPass);

        $this->setProgress(85, 'Uploading site archive...', 'upload_zip');
        $uplZip = $this->sftpUpload($zipFile, $remoteDir . '/' . basename($zipFile), $sftpHost, $sftpPort, $sftpUser, $sftpPass);

        $response['steps'][] = ['upload_db' => $uplDB];
        $response['steps'][] = ['upload_site' => $uplZip];

        $dbOk = $uplDB['ok'] ?? false;
        $zipOk = $uplZip['ok'] ?? false;

        if (! $dbOk || ! $zipOk) {
            $msg = 'SFTP upload failed (see step status)';
            $this->setProgress(90, 'Upload error (see details below)');
            $response['errors'][] = $msg;
            // add detailed messages if available
            if (is_array($uplDB) && !empty($uplDB['message'])) {
                $response['errors'][] = 'DB upload: ' . $uplDB['message'];
            }
            if (is_array($uplZip) && !empty($uplZip['message'])) {
                $response['errors'][] = 'Site upload: ' . $uplZip['message'];
            }
        } else {
            $this->setProgress(100, 'Backup completed successfully!');
        }

        return $response;
    }

    /**
     * Execute mysqldump and return structured result
     *
     * @return array<string,mixed>
     */
    public function dumpDatabase(string $host, string $user, string $pass, string $name, int $port, string $outfile): array
    {
        return $this->dumper->dump($host, $user, $pass, $name, $port, $outfile);
    }

    public function zipDirectory(string $source, string $destination): bool
    {
        if (!extension_loaded('zip')) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sourceReal = realpath($source);
        if ($sourceReal === false) {
            $zip->close();
            return false;
        }

        if (is_dir($sourceReal)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceReal), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $name => $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile()) continue;
                $filePath = $file->getRealPath();
                if ($filePath === false) continue;
                $relativePath = substr($filePath, strlen($sourceReal) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        } else {
            $zip->addFile($sourceReal, basename($sourceReal));
        }

        $zip->close();
        return file_exists($destination);
    }

    /**
     * Upload a local file to remote SFTP/SSH
     *
     * @return array<string,mixed>
     */
    public function sftpUpload(string $local, string $remote, string $host, int $port, string $user, string $pass): array
    {
        $ret = $this->uploader->upload($local, $remote, $host, $port, $user, $pass);
        $message = $ret['message'] ?? null;
        if (!is_string($message)) {
            $message = '';
        }
        $okFlag = $ret['ok'] ?? false;
        $okFlag = (bool) $okFlag;
        if ($message !== '') {
            $this->setProgress($okFlag ? 95 : 90, $message);
        }
        return $ret;
    }

    /**
     * @return array<string,bool>
     */
    public function environmentChecks(): array
    {
        $checks = [];

        $out = null; $rc = null;
        @exec('command -v mysqldump 2>/dev/null', $out, $rc);
        $checks['mysqldump'] = ($rc === 0 && !empty($out));

        $checks['zip_ext'] = extension_loaded('zip');

        $checks['phpseclib'] = class_exists(\phpseclib3\Net\SFTP::class);

        $checks['ssh2_ext'] = extension_loaded('ssh2') && function_exists('ssh2_connect');

        $tmp = $this->tmpDir;
        $checks['tmp_writable'] = is_dir($tmp) && is_writable($tmp);

        return $checks;
    }
}
