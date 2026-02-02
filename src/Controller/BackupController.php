<?php
namespace BackupApp\Controller;

use BackupApp\Model\BackupModel;
use BackupApp\Config;

class BackupController
{
    public function handle()
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

                include __DIR__ . '/../View/result.php';
                return;
            }

            $env = $model->environmentChecks();
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
