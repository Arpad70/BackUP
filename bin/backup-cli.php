#!/usr/bin/env php
<?php
declare(strict_types=1);
// Simple CLI wrapper to run backup using environment variables or JSON config file.
// Usage: bin/backup-cli.php [config.json]

require __DIR__ . '/../vendor/autoload.php';

use BackupApp\Model\BackupModel;
use BackupApp\Service\SftpKeyUploader;

function env(string $name, ?string $default = null): ?string {
    $v = getenv($name);
    return $v === false ? $default : $v;
}

// load config from file or env
$config = [];
if (isset($argv[1]) && is_readable($argv[1])) {
    $json = file_get_contents($argv[1]);
    $cfg = json_decode($json, true);
    if (is_array($cfg)) $config = $cfg;
}

// fallback to env vars
if (!isset($config['db_host'])) $config['db_host'] = env('DB_HOST', '127.0.0.1');
if (!isset($config['db_port'])) $config['db_port'] = env('DB_PORT', '3306');
if (!isset($config['db_user'])) $config['db_user'] = env('DB_USER', 'root');
if (!isset($config['db_pass'])) $config['db_pass'] = env('DB_PASS', '');
if (!isset($config['db_name'])) $config['db_name'] = env('DB_NAME', '');

if (!isset($config['sftp_host'])) $config['sftp_host'] = env('SFTP_HOST');
if (!isset($config['sftp_port'])) $config['sftp_port'] = env('SFTP_PORT', '22');
if (!isset($config['sftp_user'])) $config['sftp_user'] = env('SFTP_USER');
if (!isset($config['sftp_remote'])) $config['sftp_remote'] = env('SFTP_REMOTE', '/backups');

// If SFTP_PRIVATE_KEY env is present, use key uploader
$uploader = null;
if ($key = env('SFTP_PRIVATE_KEY')) {
    $pass = env('SFTP_KEY_PASSPHRASE');
    $uploader = new SftpKeyUploader($key, $pass ?: null);
}

$model = new BackupModel(null, $uploader);

echo "Running backup...\n";
$result = $model->runBackup($config);

echo "Result:\n";
print_r($result);

if (is_array($result) && isset($result['ok']) && $result['ok'] === true) {
    exit(0);
}
exit(1);
