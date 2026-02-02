<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Backup MVC — run backup</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;max-width:900px;margin:20px;}label{display:block;margin-top:10px;}input[type=text],input[type=password]{width:100%;padding:6px}</style>
</head>
<body>
<h1>Backup — DB dump, site zip and SFTP</h1>
<?php if (isset($env) && is_array($env)): ?>
    <div style="padding:10px;border:1px solid #ccc;margin-bottom:12px;">
        <strong>Environment check:</strong>
        <ul>
            <li>mysqldump: <?php echo $env['mysqldump'] ? '<span style="color:green">found</span>' : '<span style="color:red">missing</span>'; ?></li>
            <li>PHP Zip extension: <?php echo $env['zip_ext'] ? '<span style="color:green">loaded</span>' : '<span style="color:red">missing</span>'; ?></li>
            <li>phpseclib (composer): <?php echo $env['phpseclib'] ? '<span style="color:green">available</span>' : '<span style="color:orange">not installed</span>'; ?></li>
            <li>ssh2 extension: <?php echo $env['ssh2_ext'] ? '<span style="color:green">available</span>' : '<span style="color:orange">not available</span>'; ?></li>
            <li>Temp dir writable: <?php echo $env['tmp_writable'] ? '<span style="color:green">yes</span>' : '<span style="color:red">no</span>'; ?></li>
        </ul>
        <?php if (!$env['mysqldump'] || !$env['zip_ext'] || !$env['tmp_writable']): ?>
            <div style="color:red">Critical tools missing — the backup will not run until these are fixed.</div>
        <?php else: ?>
            <div style="color:green">Environment looks sufficient for running backups.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<form method="post">
    <h2>Database</h2>
    <label>Host <input name="db_host" type="text" value="127.0.0.1"></label>
    <label>Port <input name="db_port" type="text" value="3306"></label>
    <label>User <input name="db_user" type="text"></label>
    <label>Password <input name="db_pass" type="password"></label>
    <label>Database name <input name="db_name" type="text"></label>

    <h2>Site</h2>
    <label>Site path (absolute) <input name="site_path" type="text" placeholder="/var/www/html/example.com"></label>

    <h2>SFTP target</h2>
    <label>SFTP host <input name="sftp_host" type="text"></label>
    <label>Port <input name="sftp_port" type="text" value="22"></label>
    <label>User <input name="sftp_user" type="text"></label>
    <label>Password <input name="sftp_pass" type="password"></label>
    <label>Remote directory <input name="sftp_remote" type="text" value="/backups"></label>

    <p><button type="submit">Run backup</button></p>
    <p style="color:gray">Note: this script calls `mysqldump` and requires Zip extension. For SFTP upload it prefers phpseclib (composer) or ssh2 extension.</p>
</form>
</body>
</html>
