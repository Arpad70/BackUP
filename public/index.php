<?php
/**
 * Backup Application - Front Controller
 * MVC Entry Point - No Authentication Required
 */

// Setup error logging
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/error.log');

// Load dependencies
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
	require_once $vendorAutoload;
}

use BackupApp\Controller\BackupController;

$controller = new BackupController();
$controller->handle();
