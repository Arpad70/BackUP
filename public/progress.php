<?php
/**
 * Progress API Endpoint
 * Returns real-time backup progress from temporary file
 */

header('Content-Type: application/json');

$progressFile = isset($_GET['file']) ? $_GET['file'] : '';

// Security: only allow reading temp files with specific pattern
if (!$progressFile || !preg_match('/^backup_progress_\d+\.json$/', basename($progressFile))) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid progress file']);
    exit;
}

$fullPath = sys_get_temp_dir() . '/' . basename($progressFile);

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Progress file not found']);
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

echo json_encode($data ?: ['progress' => 0, 'message' => 'Initializing...']);
