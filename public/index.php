<?php
// Simple HTTP Basic protection with optional vlucas/phpdotenv support

$realm = 'Backup App';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
$autoloaded = false;
$envUser = null;
$envPass = null;

if (file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
	$autoloaded = true;
	if (class_exists(\Dotenv\Dotenv::class)) {
		try {
			$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
			$dotenv->safeLoad();
			$envUser = $_ENV['BACKUP_USER'] ?? $_SERVER['BACKUP_USER'] ?? getenv('BACKUP_USER') ?: null;
			$envPass = $_ENV['BACKUP_PASS'] ?? $_SERVER['BACKUP_PASS'] ?? getenv('BACKUP_PASS') ?: null;
		} catch (Throwable $e) {
			// ignore dotenv loading errors and fall back to simple parser
		}
	}
}

// Fallback: simple .env parser (keeps existing behavior if phpdotenv isn't installed)
if ($envUser === null || $envPass === null) {
	$envFile = __DIR__ . '/../.env';
	$fileEnv = [];
	if (is_readable($envFile)) {
		$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			if (strpos(trim($line), '#') === 0) continue;
			if (strpos($line, '=') === false) continue;
			list($k, $v) = explode('=', $line, 2);
			$k = trim($k);
			$v = trim($v);
			$v = trim($v, "\"'");
			$fileEnv[$k] = $v;
		}
	}
	$envUser = $fileEnv['BACKUP_USER'] ?? ($envUser ?? null);
	$envPass = $fileEnv['BACKUP_PASS'] ?? ($envPass ?? null);
}

$authUser = $envUser ?? (getenv('BACKUP_USER') ?: 'backup');
$authPass = $envPass ?? (getenv('BACKUP_PASS') ?: 'changeme');

if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $authUser || $_SERVER['PHP_AUTH_PW'] !== $authPass) {
	header('WWW-Authenticate: Basic realm="' . $realm . '"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Authentication required.';
	exit;
}

// Ensure autoload is available for the app; if we didn't require it earlier, require now if present
if (!$autoloaded && file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
}

use BackupApp\Controller\BackupController;

$controller = new BackupController();
$controller->handle();
