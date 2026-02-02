<?php
declare(strict_types=1);
namespace BackupApp\Controller;

use BackupApp\Model\BackupModel;
use BackupApp\Config;
use BackupApp\Service\SftpKeyUploader;

class BackupController
{
    public function handle(): void
    {
        // ensure application logs go to BackUP/logs/backup_app.log
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $logDir . '/backup_app.log');

        try {
            $model = new BackupModel();
            $db_config = Config::loadWordPressConfig();

            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($method === 'POST') {
                $data = array_map('trim', $_POST);

                // Handle uploaded private key file safely (read into memory, then remove)
                $privateKey = null;
                $keyMaxBytes = 16 * 1024; // 16 KB limit for private key
                $keyErrors = [];

                if (!empty($_FILES['sftp_key_file']) && is_uploaded_file($_FILES['sftp_key_file']['tmp_name'] ?? '')) {
                    $tmp = $_FILES['sftp_key_file']['tmp_name'];
                    $size = intval($_FILES['sftp_key_file']['size'] ?? 0);
                    if ($size > $keyMaxBytes) {
                        $keyErrors[] = 'Uploaded key file is too large (max 16 KB).';
                    } elseif (is_readable($tmp)) {
                        $privateKey = @file_get_contents($tmp) ?: null;
                    }
                    // remove uploaded file immediately
                    @unlink($tmp);
                }

                // if POST contains a pasted private key, prefer it over file
                if (!empty($data['sftp_auth']) && $data['sftp_auth'] === 'key') {
                    if (!empty($data['sftp_key'])) {
                        $pk = $data['sftp_key'];
                        if (strlen($pk) > $keyMaxBytes) {
                            $keyErrors[] = 'Pasted private key is too large (max 16 KB).';
                        } else {
                            $privateKey = $pk;
                        }
                    }
                }

                // Validate private key contents: must contain PEM or OpenSSH marker
                if ($privateKey !== null) {
                    $valid = false;
                    $markers = [
                        '-----BEGIN OPENSSH PRIVATE KEY-----',
                        '-----BEGIN RSA PRIVATE KEY-----',
                        '-----BEGIN DSA PRIVATE KEY-----',
                        '-----BEGIN EC PRIVATE KEY-----',
                        '-----BEGIN PRIVATE KEY-----',
                    ];
                    foreach ($markers as $m) {
                        if (strpos($privateKey, $m) !== false) { $valid = true; break; }
                    }
                    if (! $valid) {
                        $keyErrors[] = 'Private key content does not contain a recognisable PEM/OpenSSH header.';
                        // don't use this key
                        $privateKey = null;
                    }
                }

                if (!empty($keyErrors)) {
                    // add warnings/errors to result so view can render them
                    $result = ['steps' => [], 'errors' => $keyErrors];
                }

                if ($privateKey !== null) {
                    $passphrase = $data['sftp_key_passphrase'] ?? null;
                    $uploader = new SftpKeyUploader($privateKey, $passphrase ?: null);
                    // avoid keeping the key in $data or in logs
                    unset($data['sftp_key'], $data['sftp_key_passphrase']);
                    $model = new BackupModel(null, $uploader);
                    // inform user that key was used but not stored
                    $result['warnings'][] = 'Private key was used for this run and was not stored on the server.';
                }

                $result = $model->runBackup($data);
                $env = $model->environmentChecks();

                // read last 200 lines of application log for display
                $logFile = $logDir . '/backup_app.log';
                $appLog = '';
                if (is_readable($logFile)) {
                    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                    $tail = array_slice($lines, -200);
                    $appLog = implode("\n", $tail);
                }

                // pass translator to result view
                $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'cs';
                $translator = new \BackupApp\Service\Translator($lang, ['fallback' => 'cs', 'path' => dirname(__DIR__,2) . '/lang']);
                include __DIR__ . '/../View/result.php';
                return;
            }

            $env = $model->environmentChecks();
            // setup translator
            $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'cs';
            $translator = new \BackupApp\Service\Translator($lang, ['fallback' => 'cs', 'path' => dirname(__DIR__,2) . '/lang']);
            include __DIR__ . '/../View/form.php';
        } catch (\Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo '<h1>Error</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            error_log('backup_app error: ' . $e->getMessage());
        }
    }
}
