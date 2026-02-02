<?php
namespace BackupApp\Controller;

use BackupApp\Model\BackupModel;

class BackupController
{
    public function handle()
    {
        $model = new BackupModel();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $data = array_map('trim', $_POST);
            $result = $model->runBackup($data);
            $env = $model->environmentChecks();
            include __DIR__ . '/../View/result.php';
            return;
        }

        $env = $model->environmentChecks();
        include __DIR__ . '/../View/form.php';
    }
}
