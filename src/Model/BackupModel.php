<?php
namespace BackupApp\Model;

use phpseclib3\Net\SFTP;

class BackupModel
{
    protected $tmpDir;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    public function runBackup(array $data)
    {
        $response = ['steps' => [], 'errors' => []];

        $env = $this->environmentChecks();
        $response['env'] = $env;
        if (! $env['mysqldump'] || ! $env['zip_ext'] || ! $env['tmp_writable']) {
            $response['errors'][] = 'Environment incomplete: missing required tools or permissions.';
            return $response;
        }

        $dbFile = $this->tmpDir . '/db_dump_' . time() . '.sql';
        $ok = $this->dumpDatabase(
            $data['db_host'] ?? '127.0.0.1',
            $data['db_user'] ?? '',
            $data['db_pass'] ?? '',
            $data['db_name'] ?? '',
            $data['db_port'] ?? 3306,
            $dbFile
        );

        $response['steps'][] = ['db_dump' => $dbFile, 'ok' => $ok];
        if (! $ok) {
            $response['errors'][] = 'Database dump failed';
            return $response;
        }

        $sitePath = $data['site_path'] ?? '';
        if (! $sitePath || ! is_dir($sitePath)) {
            $response['errors'][] = 'Invalid site path';
            return $response;
        }

        $zipFile = $this->tmpDir . '/site_backup_' . time() . '.zip';
        $okZip = $this->zipDirectory($sitePath, $zipFile);
        $response['steps'][] = ['site_zip' => $zipFile, 'ok' => $okZip];
        if (! $okZip) {
            $response['errors'][] = 'Site zip failed';
            return $response;
        }

        $sftpHost = $data['sftp_host'] ?? '';
        $sftpPort = $data['sftp_port'] ?? 22;
        $sftpUser = $data['sftp_user'] ?? '';
        $sftpPass = $data['sftp_pass'] ?? '';
        $remoteDir = rtrim($data['sftp_remote'] ?? '.', '/');

        $uplDB = $this->sftpUpload($dbFile, $remoteDir . '/' . basename($dbFile), $sftpHost, $sftpPort, $sftpUser, $sftpPass);
        $uplZip = $this->sftpUpload($zipFile, $remoteDir . '/' . basename($zipFile), $sftpHost, $sftpPort, $sftpUser, $sftpPass);

        $response['steps'][] = ['upload_db' => $uplDB];
        $response['steps'][] = ['upload_site' => $uplZip];

        if (! $uplDB || ! $uplZip) {
            $response['errors'][] = 'SFTP upload failed (see step status)';
        }

        return $response;
    }

    public function dumpDatabase($host, $user, $pass, $name, $port, $outfile)
    {
        $host = escapeshellarg($host);
        $user = escapeshellarg($user);
        $pass = escapeshellarg($pass);
        $name = escapeshellarg($name);
        $port = (int)$port;
        $outfileEsc = escapeshellarg($outfile);

        $cmd = "mysqldump --host={$host} --port={$port} --user={$user} --password={$pass} --single-transaction --quick --routines --triggers {$name} > {$outfileEsc} 2>&1";

        exec($cmd, $out, $rc);
        return $rc === 0 && file_exists($outfile);
    }

    public function zipDirectory($source, $destination)
    {
        if (!extension_loaded('zip')) {
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source = realpath($source);
        if (is_dir($source)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $name => $file) {
                if (! $file->isFile()) continue;
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        } else {
            $zip->addFile($source, basename($source));
        }

        $zip->close();
        return file_exists($destination);
    }

    public function sftpUpload($local, $remote, $host, $port, $user, $pass)
    {
        if (!file_exists($local)) {
            return false;
        }
        if (class_exists(SFTP::class)) {
            try {
                $sftp = new SFTP($host, (int)$port);
                if (! $sftp->login($user, $pass)) {
                    return false;
                }
                $remoteDir = dirname($remote);
                $sftp->mkdir($remoteDir, -1, true);
                return $sftp->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
            } catch (\Throwable $e) {
                return false;
            }
        }

        if (function_exists('ssh2_connect')) {
            $connection = @\ssh2_connect($host, $port);
            if (! $connection) return false;
            if (! @\ssh2_auth_password($connection, $user, $pass)) return false;
            $sftp = \ssh2_sftp($connection);
            $remoteStream = @fopen("ssh2.sftp://" . intval($sftp) . $remote, 'w');
            if (! $remoteStream) return false;
            $data_to_send = @file_get_contents($local);
            if ($data_to_send === false) return false;
            fwrite($remoteStream, $data_to_send);
            fclose($remoteStream);
            return true;
        }

        return false;
    }

    public function environmentChecks()
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
