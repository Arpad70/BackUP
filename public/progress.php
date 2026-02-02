<?php
/**
 * Progress API Endpoint
 * Returns real-time backup progress from temporary file
 */

header('Content-Type: application/json');

$progressFile = isset($_GET['file']) ? $_GET['file'] : '';

// optional lang parameter for localized messages
$lang = $_GET['lang'] ?? 'cs';

// try to load autoloader and translator if available
$translator = null;
$vendor = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor)) {
    require_once $vendor;
    try {
        $translator = new \BackupApp\Service\Translator($lang, ['fallback' => 'cs', 'path' => __DIR__ . '/../lang']);
    } catch (\Throwable $e) {
        $translator = null;
    }
}

// Security: only allow reading temp files with specific pattern
if (!$progressFile || !preg_match('/^backup_progress_\d+\.json$/', basename($progressFile))) {
    http_response_code(400);
    $msg = $translator ? $translator->translate('invalid_progress_file') : 'Invalid progress file';
    echo json_encode(['error' => $msg]);
    exit;
}

$fullPath = sys_get_temp_dir() . '/' . basename($progressFile);

if (!file_exists($fullPath)) {
    http_response_code(404);
    $msg = $translator ? $translator->translate('progress_file_not_found') : 'Progress file not found';
    echo json_encode(['error' => $msg]);
    exit;
}

$content = file_get_contents($fullPath);
$data = json_decode($content, true);

// Clean up old files (older than 1 hour)
$allFiles = glob(sys_get_temp_dir() . '/backup_progress_*.json');
foreach ($allFiles as $file) {
    if (filemtime($file) < time() - 3600) {
        @unlink($file);
    }
}

$initMsg = $translator ? $translator->translate('initializing') : 'Initializing...';
echo json_encode($data ?: ['progress' => 0, 'message' => $initMsg]);
