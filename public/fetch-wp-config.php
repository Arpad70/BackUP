<?php
// public/fetch-wp-config.php
// POST: site_path (absolute) -> returns JSON with DB settings

header('Content-Type: application/json; charset=utf-8');

$site = $_POST['site_path'] ?? null;
if (!$site) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing site_path']);
    exit;
}

$site = rtrim($site, "\/\\");
// Basic validation: must be absolute path
if ($site[0] !== '/') {
    http_response_code(400);
    echo json_encode(['error' => 'site_path must be absolute']);
    exit;
}

$wp = $site . DIRECTORY_SEPARATOR . 'wp-config.php';
if (!is_readable($wp)) {
    http_response_code(404);
    echo json_encode(['error' => 'wp-config.php not found or not readable', 'path' => $wp]);
    exit;
}

$content = file_get_contents($wp);
// remove block comments and simple // comments for safer parsing
$clean = preg_replace('#/\*.*?\*/#s', '', $content);
$clean = preg_replace('#//.*#', '', $clean);

function extract_const($name, $str) {
    $pat = '/define\s*\(\s*["\']' . preg_quote($name, '/') . '["\']\s*,\s*(["\'])(.*?)\1\s*\)/s';
    if (preg_match($pat, $str, $m)) return $m[2];
    return null;
}

$res = [
    'DB_NAME' => extract_const('DB_NAME', $clean),
    'DB_USER' => extract_const('DB_USER', $clean),
    'DB_PASSWORD' => extract_const('DB_PASSWORD', $clean),
    'DB_HOST' => extract_const('DB_HOST', $clean),
    'DB_CHARSET' => extract_const('DB_CHARSET', $clean),
    'DB_COLLATE' => extract_const('DB_COLLATE', $clean),
];

if (preg_match('/\\$table_prefix\s*=\s*(["\'])(.*?)\1\s*;/', $clean, $m)) {
    $res['table_prefix'] = $m[2];
}

echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
