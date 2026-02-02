<?php
declare(strict_types=1);
namespace BackupApp\Model;

use BackupApp\Service\DatabaseDumper;
use BackupApp\Contract\UploaderInterface;
use BackupApp\Service\SftpUploader;

class BackupModel
{
    protected string $tmpDir;
    private ?string $progressFile = null;
    private DatabaseDumper $dumper;
    private UploaderInterface $uploader;

    public function __construct(?DatabaseDumper $dumper = null, ?UploaderInterface $uploader = null)
    {
        $this->tmpDir = sys_get_temp_dir();
        $this->dumper = $dumper ?? new DatabaseDumper();
        $this->uploader = $uploader ?? new SftpUploader();
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

        // Prepare target artifacts: target DB dump and target site zip if possible
        $targetDbFile = null;
        $targetZipFile = null;
        $siteName = basename(rtrim($sitePath, '/')) ?: 'site';

        // target DB credentials (optional)
        $tDbHost = $data['target_db_host'] ?? null;
        if (is_string($tDbHost) && $tDbHost !== '') {
            $this->setProgress(50, 'Dumping target database...', 'target_db_dump');
            $targetDbFile = $this->tmpDir . '/target_db_dump_' . time() . '.sql';
                $tDbUser = $data['target_db_user'] ?? '';
                if (!is_string($tDbUser)) $tDbUser = '';
                $tDbPass = $data['target_db_pass'] ?? '';
                if (!is_string($tDbPass)) $tDbPass = '';
                $tDbName = $data['target_db_name'] ?? '';
                if (!is_string($tDbName)) $tDbName = '';
            $tDbPort = $data['target_db_port'] ?? null;
            if (is_string($tDbPort) && ctype_digit($tDbPort)) {
                $tDbPort = (int)$tDbPort;
            } elseif (!is_int($tDbPort)) {
                $tDbPort = 3306;
            }
            $tRes = $this->dumpDatabase($tDbHost, $tDbUser, $tDbPass, $tDbName, (int)$tDbPort, $targetDbFile);
            $response['steps'][] = ['target_db_dump' => $tRes];
        }

        // target site zip if target path provided locally
        $targetPath = $data['target_site_path'] ?? null;
        if (is_string($targetPath) && $targetPath !== '') {
            if (!is_dir($targetPath)) {
                if (!@mkdir($targetPath, 0755, true)) {
                    $response['errors'][] = 'Failed to create target path: ' . $targetPath;
                    $this->setProgress(80, 'Error creating target path');
                    return $response;
                }
            }
            $this->setProgress(60, 'Compressing target site files...', 'target_zip');
            $targetZipFile = $this->tmpDir . '/target_site_backup_' . time() . '.zip';
            $okTargetZip = $this->zipDirectory($targetPath, $targetZipFile);
            $response['steps'][] = ['target_site_zip' => $targetZipFile, 'ok' => $okTargetZip];
            if (! $okTargetZip) {
                $response['errors'][] = 'Target site zip failed';
            }
        }

        // list of artifact files to include in final combined archive
        $artifacts = [];
        $artifacts[] = ['path' => $dbFile, 'name' => basename($dbFile)];
        $artifacts[] = ['path' => $zipFile, 'name' => basename($zipFile)];
        if ($targetDbFile !== null) $artifacts[] = ['path' => $targetDbFile, 'name' => basename($targetDbFile)];
        if ($targetZipFile !== null) $artifacts[] = ['path' => $targetZipFile, 'name' => basename($targetZipFile)];

        // determine where to place backups: local target path or remote sftp
        $remoteDir = $data['sftp_remote'] ?? '';
        if (!is_string($remoteDir)) $remoteDir = '';
        $remoteDir = rtrim($remoteDir, '/');
        $sftpHost = $data['sftp_host'] ?? null;
        $useSftp = false;
        if ((is_string($sftpHost) && $sftpHost !== '') && (empty($targetPath))) {
            $useSftp = true;
        }

        $backupsRel = 'backups';

        if (is_string($targetPath) && $targetPath !== '' && is_dir($targetPath)) {
            // copy artifacts into target/backups
            $backupsDir = rtrim($targetPath, '/') . '/' . $backupsRel;
            if (!is_dir($backupsDir)) @mkdir($backupsDir, 0755, true);
            foreach ($artifacts as $a) {
                $path = $a['path'];
                $name = $a['name'];
                if ($path !== '' && is_string($path) && file_exists($path)) {
                    @copy($path, $backupsDir . '/' . $name);
                }
            }
            // create combined zip locally from artifact files
            $rand = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $combinedName = 'backup_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $siteName) . '_' . date('YmdHis') . '_' . $rand . '.zip';
            $combinedLocal = $this->tmpDir . '/' . $combinedName;
            $zip = new \ZipArchive();
                if ($zip->open($combinedLocal, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($artifacts as $a) {
                    $path = $a['path'];
                    $name = $a['name'];
                    if ($path !== '' && is_string($path) && file_exists($path)) $zip->addFile($path, $name);
                }
                $zip->close();
                // copy combined to target backups
                @copy($combinedLocal, $backupsDir . '/' . $combinedName);
                $response['steps'][] = ['combined' => $backupsDir . '/' . $combinedName, 'local_copy' => $combinedLocal];
            } else {
                $response['errors'][] = 'Failed to create combined archive';
            }

            $this->setProgress(100, 'Backup completed (local target)');
            return $response;
        }

        // else use SFTP to upload artifacts to remote backups dir and upload combined archive
        $sftpHost = $data['sftp_host'] ?? '';
        if (!is_string($sftpHost)) $sftpHost = '';
        $sftpPort = $data['sftp_port'] ?? 22;
        if (is_string($sftpPort) && ctype_digit($sftpPort)) {
            $sftpPort = (int)$sftpPort;
        } elseif (!is_int($sftpPort)) {
            $sftpPort = 22;
        }
        $sftpUser = $data['sftp_user'] ?? '';
        if (!is_string($sftpUser)) $sftpUser = '';
        $sftpPass = $data['sftp_pass'] ?? '';
        if (!is_string($sftpPass)) $sftpPass = '';
        $remoteBackups = ($remoteDir !== '') ? $remoteDir . '/' . $backupsRel : $backupsRel;

        // ensure remote backups directory exists (best-effort via uploader creating dirs)
        foreach ($artifacts as $a) {
            $path = $a['path'];
            $name = $a['name'];
            $this->setProgress(70, 'Uploading ' . $name . '...', 'upload');
            $this->sftpUpload($path, $remoteBackups . '/' . $name, $sftpHost, (int)$sftpPort, $sftpUser, $sftpPass);
        }

        // create combined locally and upload
        $rand = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $combinedName = 'backup_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $siteName) . '_' . date('YmdHis') . '_' . $rand . '.zip';
        $combinedLocal = $this->tmpDir . '/' . $combinedName;
        $zip = new \ZipArchive();
        if ($zip->open($combinedLocal, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($artifacts as $a) {
                $path = $a['path'];
                $name = $a['name'];
                if ($path !== '' && is_string($path) && file_exists($path)) $zip->addFile($path, $name);
            }
            $zip->close();
            $this->sftpUpload($combinedLocal, $remoteBackups . '/' . $combinedName, $sftpHost, (int)$sftpPort, $sftpUser, $sftpPass);
            $response['steps'][] = ['combined' => $remoteBackups . '/' . $combinedName, 'local_copy' => $combinedLocal];
            $this->setProgress(100, 'Backup completed (remote target)');
        } else {
            $response['errors'][] = 'Failed to create combined archive';
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
     * Recursively copy directory contents from source to destination.
     */
    public function recursiveCopy(string $source, string $dest): bool
    {
        $sourceReal = realpath($source);
        if ($sourceReal === false) return false;

        // create destination if needed
        if (!is_dir($dest)) {
            if (!@mkdir($dest, 0755, true)) {
                return false;
            }
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceReal, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            /** @var \SplFileInfo $item */
            $targetPath = rtrim($dest, '/') . '/' . ltrim(str_replace($sourceReal, '', $item->getRealPath()), '/');
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0755, true);
                }
            } else {
                // copy file
                if (!@copy($item->getRealPath(), $targetPath)) {
                    return false;
                }
                // try to preserve perms
                @chmod($targetPath, $item->getPerms() & 0777);
            }
        }

        return true;
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
