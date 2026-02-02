<?php
declare(strict_types=1);
namespace BackupApp\Service;

use BackupApp\Contract\UploaderInterface;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

class SftpKeyUploader implements UploaderInterface
{
    private string $privateKey;
    private ?string $passphrase;

    public function __construct(string $privateKey, ?string $passphrase = null)
    {
        $this->privateKey = $privateKey;
        $this->passphrase = $passphrase;
    }

    /**
     * Upload using an in-memory private key. The interface's $pass param is ignored.
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

        if (! class_exists(SFTP::class) || ! class_exists(PublicKeyLoader::class)) {
            $msg = 'phpseclib3 is required for key-based SFTP uploads';
            error_log($msg);
            return ['ok' => false, 'message' => $msg];
        }

        try {
            $sftp = new SFTP($host, $port);
            /** @var \phpseclib3\Crypt\Common\PrivateKey $key */
            $key = PublicKeyLoader::load($this->privateKey, $this->passphrase ?? '');
            if (! $sftp->login($user, $key)) {
                $msg = 'SFTP key login failed for user ' . $user . ' on ' . $host . ':' . $port;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }

            $remoteDir = dirname($remote);
            if (! $sftp->is_dir($remoteDir)) {
                // best-effort create
                @$sftp->mkdir($remoteDir, -1, true);
            }

            $ok = $sftp->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
            if (! $ok) {
                $msg = 'SFTP put failed for ' . $remote . ' to ' . $host;
                error_log($msg);
                return ['ok' => false, 'message' => $msg];
            }

            $bytes = filesize($local) ?: 0;
            $msg = 'Uploaded ' . basename($local) . ' to ' . $host . ':' . $remote . ' (key auth)';
            return ['ok' => true, 'message' => $msg, 'bytes' => $bytes];
        } catch (\Throwable $e) {
            $msg = 'SFTP key exception: ' . $e->getMessage();
            error_log($msg);
            return ['ok' => false, 'message' => $msg];
        }
    }
}
