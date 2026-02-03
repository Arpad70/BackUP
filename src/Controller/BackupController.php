<?php
declare(strict_types=1);
namespace BackupApp\Controller;

use BackupApp\Model\BackupModel;
use BackupApp\Model\DatabaseCredentials;
use BackupApp\Config;
use BackupApp\Service\SftpKeyUploader;
use BackupApp\Migration\MigrationStepRegistry;
use BackupApp\Container\ServiceContainer;

class BackupController
{
    private ?ServiceContainer $container;

    public function __construct(?ServiceContainer $container = null)
    {
        $this->container = $container;
    }
    public function handle(): void
    {
        // Initialize service container
        $container = new ServiceContainer();
        $logDir = $container->getLogDir();
        ini_set('error_log', $logDir . '/backup_app.log');

        try {
            // Start session to preserve result data when changing language
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            // determine language and create translator early so we can use it during POST handling
            $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'cs';
            $translator = $container->getTranslator($lang);
            $model = $container->getBackupModel();

            // Handle AJAX migration steps
            if (!empty($_POST) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $this->handleMigrationStep($container);
                return;
            }

            // Handle non-AJAX migration steps
            if (!empty($_GET['action']) && $_GET['action'] === 'migration_step') {
                $this->handleMigrationStep($container);
                return;
            }

            $model = $container->getBackupModel();

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
                    $model = $container->getBackupModel($uploader);
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
                $backup_data = [
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
                $_SESSION['last_backup_data'] = $backup_data;

                // pass translator to result view (translator was already created earlier)
                $showResult = false;
                extract(compact('translator', 'result', 'model', 'env', 'appLog', 'showResult', 'backup_data'));
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
                extract(compact('translator', 'result', 'model', 'env', 'appLog', 'showResult'));
                include __DIR__ . '/../View/result.php';
                return;
            }

            // Check if going to migration page
            if (!empty($_GET['page']) && $_GET['page'] === 'migration') {
                $backupData = $_SESSION['backup_result']['result']['backup_data'] ?? $_SESSION['last_backup_data'] ?? [];
                extract(compact('translator', 'model', 'backupData'));
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

    private function handleMigrationStep(ServiceContainer $container): void
    {
        header('Content-Type: application/json');

        $rawInput = file_get_contents('php://input');
        $input = (is_string($rawInput) ? json_decode($rawInput, true) : null) ?? $_POST;
        if (!is_array($input)) {
            $input = $_POST;
        }
        $step = $input['step'] ?? null;
        $backupData = $input['backupData'] ?? $_SESSION['last_backup_data'] ?? [];
        $method = $input['method'] ?? 'local';
        
        $translator = $container->getTranslator();
        $model = $container->getBackupModel();

        if (empty($step)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing step parameter']);
            return;
        }

        $result = [];

        try {
            // Add step-specific parameters to backupData
            if ($step === 'search_replace') {
                $backupData['search_from'] = $input['search_from'] ?? '';
                $backupData['search_to'] = $input['search_to'] ?? '';
                $backupData['dry_run'] = $input['dry_run'] ?? true;
            }

            // Use registry for steps that have been refactored
            $registry = $container->get('migration_registry');
            
            // Pre-migration steps handled by registry
            if ($registry instanceof \BackupApp\Migration\MigrationStepRegistry && $registry->has($step)) {
                $result = $registry->execute($step, $backupData);
                echo json_encode($result);
                return;
            }

            // Remaining steps handled here (not yet refactored)
            switch ($step) {
                case 'clear':
                    if (empty($backupData['target_path'])) {
                        throw new \Exception('Target path is required');
                    }
                    $result = $model->clearTargetDirectory($backupData['target_path']);
                    break;

                case 'extract':
                    if (empty($backupData['target_path'])) {
                        throw new \Exception('Target path is required');
                    }
                    // Find the latest backup file
                    $backupDir = $container->getAppRoot() . '/backups';
                    $files = @glob($backupDir . '/backup_*.zip') ?: [];
                    if (empty($files)) {
                        throw new \Exception('No backup file found');
                    }
                    $latestBackup = end($files);
                    $result = $model->extractBackupArchive($latestBackup, $backupData['target_path']);
                    break;

                case 'reset_db':
                    if (empty($backupData['target_path']) || empty($backupData['target_db'])) {
                        throw new \Exception('Target path and database are required');
                    }
                    $dbCredentials = DatabaseCredentials::fromTargetArray($backupData);
                    $result = $model->resetTargetDatabase(
                        $backupData['target_path'],
                        $dbCredentials
                    );
                    break;

                case 'import_db':
                    if (empty($backupData['target_path']) || empty($backupData['target_db'])) {
                        throw new \Exception('Target path and database are required');
                    }
                    // Find the latest SQL dump
                    $backupDir = $container->getAppRoot() . '/backups';
                    $files = @glob($backupDir . '/db_dump_*.sql') ?: [];
                    if (empty($files)) {
                        throw new \Exception('No database dump file found');
                    }
                    $latestDump = end($files);
                    $dbCredentials = DatabaseCredentials::fromTargetArray($backupData);
                    $result = $model->importTargetDatabase(
                        $backupData['target_path'],
                        $latestDump,
                        $dbCredentials
                    );
                    break;

                default:
                    throw new \Exception('Unknown step: ' . $step);
            }

            echo json_encode([
                'success' => ($result['ok'] ?? false),
                'output' => $result['message'] ?? 'Step completed',
                'result' => $result,
                'error' => ($result['ok'] ?? false) ? null : ($result['message'] ?? 'Unknown error')
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'output' => 'Error: ' . $e->getMessage()
            ]);
            error_log('migration_step error: ' . $e->getMessage());
        }
    }

    /**
     * Handle GET requests - render form
     */
    public function handleGet(): void
    {
        $container = $this->container ?? new ServiceContainer();
        $lang = $_GET['lang'] ?? $_COOKIE['lang'] ?? 'cs';
        $translator = $container->getTranslator($lang);
        $model = $container->getBackupModel();
        
        // Environment checks
        $env = $model->environmentChecks();
        
        // Make variables available to view
        extract(compact('translator', 'model', 'env'));
        
        // Include form view
        include __DIR__ . '/../View/form.php';
    }

    /**
     * Handle POST requests - process form
     */
    public function handlePost(): void
    {
        // This calls the main handle() which processes POST
        // For now, just delegate to handle()
        $this->handle();
    }
}
