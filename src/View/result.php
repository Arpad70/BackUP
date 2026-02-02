<!doctype html>
<html>
<head><meta charset="utf-8"><title>Backup result</title></head>
<body>
<h1>Backup result</h1>
<?php if (!empty($result['errors'])): ?>
    <div style="color:red"><strong>Errors:</strong>
        <ul><?php foreach($result['errors'] as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
    </div>
<?php endif; ?>
<?php if (isset($env) && is_array($env)): ?>
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
        <li><?php echo htmlspecialchars(json_encode($s)); ?></li>
    <?php endforeach; ?>
</ul>
<p><a href="./">Back</a></p>
</body>
</html>
