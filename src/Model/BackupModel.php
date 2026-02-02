<?php
declare(strict_types=1);
namespace BackupApp\Model;

use phpseclib3\Net\SFTP;

class BackupModel
{
    /** @var string */
    protected $tmpDir;
    /** @var string|null */
    private $progressFile = null;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
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
        $dbResult = $this->dumpDatabase(
            $data['db_host'] ?? '127.0.0.1',
            $data['db_user'] ?? '',
            $data['db_pass'] ?? '',
            $data['db_name'] ?? '',
            $data['db_port'] ?? 3306,
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

        $sitePath = $data['site_path'] ?? '';
        if (! $sitePath || ! is_dir($sitePath)) {
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

        $sftpHost = $data['sftp_host'] ?? '';
        $sftpPort = $data['sftp_port'] ?? 22;
        $sftpUser = $data['sftp_user'] ?? '';
        $sftpPass = $data['sftp_pass'] ?? '';
        $remoteDir = rtrim($data['sftp_remote'] ?? '.', '/');

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
        $hostArg = escapeshellarg($host);
        $userArg = escapeshellarg($user);
        $passArg = escapeshellarg($pass);
        $nameArg = escapeshellarg($name);
        $port = (int)$port;
        $outfileEsc = escapeshellarg($outfile);

        $cmd = "mysqldump --host={$hostArg} --port={$port} --user={$userArg} --password={$passArg} --single-transaction --quick --routines --triggers {$nameArg} > {$outfileEsc} 2>&1";

        exec($cmd, $out, $rc);
        $outText = trim(implode("\n", $out));
        if ($rc === 0 && file_exists($outfile)) {
            $msg = $outText ?: 'Dump created';
            return ['ok' => true, 'message' => $msg];
        }
        $msg = $outText ?: 'mysqldump failed with exit code ' . intval($rc);
        error_log('dumpDatabase: ' . $msg . ' -- cmd: ' . $cmd);
        return ['ok' => false, 'message' => $msg];
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
        if (!file_exists($local)) {
            $msg = 'Local file not found: ' . $local;
            error_log($msg);
            $this->setProgress(90, $msg);
            return ['ok' => false, 'message' => $msg];
        }
        if (class_exists(SFTP::class)) {
            try {
                $sftp = new SFTP($host, (int)$port);
                if (! $sftp->login($user, $pass)) {
                    $msg = 'SFTP login failed for user ' . $user . ' on ' . $host . ':' . $port;
                    error_log($msg);
                    $this->setProgress(90, $msg);
                    return ['ok' => false, 'message' => $msg];
                }
                $remoteDir = dirname($remote);
                if (! $sftp->mkdir($remoteDir, -1, true)) {
                    $msg = 'SFTP mkdir failed: ' . $remoteDir;
                    error_log($msg);
                    $this->setProgress(90, $msg);
                    // continue, put() may still create directory depending on server
                }
                $ok = $sftp->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
                if (! $ok) {
                    $msg = 'SFTP put failed for ' . $remote . ' to ' . $host;
                    error_log($msg);
                    $this->setProgress(90, $msg);
                    return ['ok' => false, 'message' => $msg];
                }
                $msg = 'Uploaded ' . basename($local) . ' to ' . $host . ':' . $remote;
                return ['ok' => true, 'message' => $msg];
            } catch (\Throwable $e) {
                $msg = 'SFTP exception: ' . $e->getMessage();
                error_log($msg);
                $this->setProgress(90, $msg);
                return ['ok' => false, 'message' => $msg];
            }
        }

        if (function_exists('ssh2_connect')) {
            $connection = @\ssh2_connect($host, $port);
            if (! $connection) {
                $msg = 'ssh2_connect failed to ' . $host . ':' . $port;
                error_log($msg);
                $this->setProgress(90, $msg);
                return ['ok' => false, 'message' => $msg];
            }
            if (! @\ssh2_auth_password($connection, $user, $pass)) {
                $msg = 'ssh2_auth_password failed for user ' . $user . ' on ' . $host;
                error_log($msg);
                $this->setProgress(90, $msg);
                return ['ok' => false, 'message' => $msg];
            }
            $sftp = \ssh2_sftp($connection);
            $remoteStream = @fopen("ssh2.sftp://" . intval($sftp) . $remote, 'w');
            if (! $remoteStream) {
                $msg = 'ssh2 fopen failed for remote path: ' . $remote;
                error_log($msg);
                $this->setProgress(90, $msg);
                return ['ok' => false, 'message' => $msg];
            }
            $data_to_send = @file_get_contents($local);
            if ($data_to_send === false) {
                $msg = 'Failed reading local file: ' . $local;
                error_log($msg);
                $this->setProgress(90, $msg);
                return ['ok' => false, 'message' => $msg];
            }
            fwrite($remoteStream, $data_to_send);
            fclose($remoteStream);
            $msg = 'Uploaded ' . basename($local) . ' to ' . $host . ':' . $remote . ' via ssh2';
            return ['ok' => true, 'message' => $msg];
        }

        $msg = 'No SFTP method available (phpseclib or ssh2)';
        error_log($msg);
        $this->setProgress(90, $msg);
        return ['ok' => false, 'message' => $msg];
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
