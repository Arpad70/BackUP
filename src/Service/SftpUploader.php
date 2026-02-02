<?php
declare(strict_types=1);
namespace BackupApp\Service;

use phpseclib3\Net\SFTP;

class SftpUploader
{
    /**
     * Upload a local file to remote SFTP/SSH. Returns structured array.
     *
     * @return array<string,mixed>
     */
    public function upload(string $local, string $remote, string $host, int $port, string $user, string $pass): array
    {
        if (!file_exists($local)) {
            $msg = 'Local file not found: ' . $local;
            error_log($msg);
            return ['ok' => false, 'message' => $msg];
        }

        if (class_exists(SFTP::class)) {
            try {
                $sftp = new SFTP($host, $port);
                if (! $sftp->login($user, $pass)) {
                    $msg = 'SFTP login failed for user ' . $user . ' on ' . $host . ':' . $port;
                    error_log($msg);
                    return ['ok' => false, 'message' => $msg];
                }
                $remoteDir = dirname($remote);
                if (! $sftp->mkdir($remoteDir, -1, true)) {
                    // not fatal
                }
                $ok = $sftp->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
                if (! $ok) {
                    $msg = 'SFTP put failed for ' . $remote . ' to ' . $host;
                    error_log($msg);
                    return ['ok' => false, 'message' => $msg];
                }
                $msg = 'Uploaded ' . basename($local) . ' to ' . $host . ':' . $remote;
                return ['ok' => true, 'message' => $msg];
            } catch (\Throwable $e) {
                $msg = 'SFTP exception: ' . $e->getMessage();
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }
        }

        if (function_exists('ssh2_connect')) {
            $connection = @\ssh2_connect($host, $port);
            if (! $connection) {
                $msg = 'ssh2_connect failed to ' . $host . ':' . $port;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }
            if (! @\ssh2_auth_password($connection, $user, $pass)) {
                $msg = 'ssh2_auth_password failed for user ' . $user . ' on ' . $host;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }
            $sftp = \ssh2_sftp($connection);
            $remoteStream = @fopen("ssh2.sftp://" . intval($sftp) . $remote, 'w');
            if (! $remoteStream) {
                $msg = 'ssh2 fopen failed for remote path: ' . $remote;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }
            $data_to_send = @file_get_contents($local);
            if ($data_to_send === false) {
                $msg = 'Failed reading local file: ' . $local;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }
            fwrite($remoteStream, $data_to_send);
            fclose($remoteStream);
            $msg = 'Uploaded ' . basename($local) . ' to ' . $host . ':' . $remote . ' via ssh2';
            return ['ok' => true, 'message' => $msg];
        }

        $msg = 'No SFTP method available (phpseclib or ssh2)';
        error_log($msg);
        return ['ok' => false, 'message' => $msg];
    }
}
