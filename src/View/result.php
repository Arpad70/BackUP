<?php
// Ensure expected variables exist to avoid undefined warnings in views/tests
if (!isset($result) || !is_array($result)) {
    $result = ['steps' => [], 'errors' => []];
}
if (!isset($env) || !is_array($env)) {
    $env = [];
}
if (!isset($appLog)) {
    $appLog = '';
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Backup result</title></head>
<body>
<div class="container py-4">
    <h1 class="mb-3">Backup result</h1>
<?php if (!empty($result['errors'])): ?>
    <div style="color:red"><strong>Errors:</strong>
        <ul><?php foreach($result['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
<?php endif; ?>
<?php if (!empty($result['warnings'])): ?>
    <div id="toast" style="position:fixed;right:20px;top:20px;background:#333;color:#fff;padding:12px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,.2);">
        <?php foreach($result['warnings'] as $w) echo '<div>'.htmlspecialchars($w).'</div>'; ?>
    </div>
    <script>setTimeout(()=>{const t=document.getElementById('toast'); if(t) t.style.display='none'},8000);</script>
<?php endif; ?>
<?php if (!empty($env)): ?>
    <h2>Environment diagnostics</h2>
    <ul>
        <li>mysqldump: <?php echo $env['mysqldump'] ? 'found' : 'missing'; ?></li>
        <li>PHP Zip: <?php echo $env['zip_ext'] ? 'loaded' : 'missing'; ?></li>
        <li>phpseclib: <?php echo $env['phpseclib'] ? 'available' : 'not installed'; ?></li>
        <li>ssh2 ext: <?php echo $env['ssh2_ext'] ? 'available' : 'not available'; ?></li>
        <li>tmp writable: <?php echo $env['tmp_writable'] ? 'yes' : 'no'; ?></li>
    </ul>
<?php endif; ?>
<h2>Steps</h2>
<ul>
    <?php foreach($result['steps'] as $s): ?>
        <li>
            <?php
            // Each step is an associative array; render keys and messages nicely
            foreach ($s as $key => $val) {
                if (is_array($val)) {
                    $ok = !empty($val['ok']) ? 'ok' : 'failed';
                    $msg = !empty($val['message']) ? ' — ' . htmlspecialchars($val['message']) : '';
                    echo '<strong>' . htmlspecialchars($key) . '</strong>: ' . htmlspecialchars($ok) . $msg;
                } else {
                    // simple value (path or boolean)
                    if (is_bool($val)) {
                        echo '<strong>' . htmlspecialchars($key) . '</strong>: ' . ($val ? 'ok' : 'failed');
                    } else {
                        echo '<strong>' . htmlspecialchars($key) . '</strong>: ' . htmlspecialchars($val);
                    }
                }
            }
            ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (!empty($appLog)): ?>
    <h2>Application log</h2>
    <pre style="background:#f8f8f8;padding:10px;border:1px solid #ddd;white-space:pre-wrap;"><?php echo htmlspecialchars($appLog); ?></pre>
<?php endif; ?>
<p><a href="./">Back</a></p>
</body>
</html>
