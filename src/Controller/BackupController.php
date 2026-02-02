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
            // Start session to preserve result data when changing language
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $db_config = Config::loadWordPressConfig();

            // determine language and create translator early so we can use it during POST handling
            $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'cs';
            $translator = new \BackupApp\Service\Translator($lang, ['fallback' => 'cs', 'path' => dirname(__DIR__,2) . '/lang']);
            $model = new BackupModel(null, null, $translator);

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
                        $keyErrors[] = $translator->translate('uploaded_key_too_large');
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
                            $keyErrors[] = $translator->translate('pasted_key_too_large');
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
                        $keyErrors[] = $translator->translate('private_key_invalid_header');
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
                    $model = new BackupModel(null, $uploader, $translator);
                    // inform user that key was used but not stored
                    $result['warnings'][] = $translator->translate('private_key_used_warning');
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

                // Store result in session to preserve when changing language
                $_SESSION['backup_result'] = [
                    'result' => $result,
                    'env' => $env,
                    'appLog' => $appLog
                ];
                
                // Also store backup data for migration page
                $_SESSION['last_backup_data'] = [
                    'source_path' => $data['source_path'] ?? '',
                    'target_path' => $data['target_path'] ?? '',
                    'source_db' => $data['source_db'] ?? '',
                    'source_db_host' => $data['source_db_host'] ?? '',
                    'source_db_port' => $data['source_db_port'] ?? '',
                    'source_db_user' => $data['source_db_user'] ?? '',
                    'target_db' => $data['target_db'] ?? '',
                    'target_db_host' => $data['target_db_host'] ?? '',
                    'target_db_port' => $data['target_db_port'] ?? '',
                    'target_db_user' => $data['target_db_user'] ?? '',
                ];

                // pass translator to result view (translator was already created earlier)
                include __DIR__ . '/../View/result.php';
                return;
            }

            // Check if we have stored result (from language change on result page)
            $result = null;
            $env = null;
            $appLog = '';
            $showResult = false;
            
            if (!empty($_SESSION['backup_result'])) {
                $stored = $_SESSION['backup_result'];
                $result = $stored['result'] ?? null;
                $env = $stored['env'] ?? null;
                $appLog = $stored['appLog'] ?? '';
                $showResult = true;
            }

            if ($showResult && !empty($_GET['lang'])) {
                // Language changed on result page - show result with new language
                include __DIR__ . '/../View/result.php';
                return;
            }

            // Check if going to migration page
            if (!empty($_GET['page']) && $_GET['page'] === 'migration') {
                $backupData = $_SESSION['backup_result']['result']['backup_data'] ?? $_SESSION['last_backup_data'] ?? [];
                include __DIR__ . '/../View/migration.php';
                return;
            }

            // Normal form page load
            if (empty($env)) {
                $env = $model->environmentChecks();
            }
            include __DIR__ . '/../View/form.php';
        } catch (\Throwable $e) {
            header('HTTP/1.1 500 Internal Server Error');
            $t = $translator ?? new \BackupApp\Service\Translator('cs', ['fallback' => 'cs', 'path' => dirname(__DIR__,2) . '/lang']);
            echo '<h1>' . htmlspecialchars($t->translate('error_heading')) . '</h1>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            error_log('backup_app error: ' . $e->getMessage());
        }
    }
}
