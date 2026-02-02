<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Backup MVC — run backup</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; max-width: 900px; margin: 20px; }
        label { display: block; margin-top: 10px; }
        input[type=text], input[type=password] { width: 100%; padding: 6px; }
        .success { color: green; padding: 10px; border: 1px solid green; margin-bottom: 12px; }
        .error { color: red; padding: 10px; border: 1px solid red; margin-bottom: 12px; }
        .progress-container { display: none; text-align: center; margin: 20px 0; }
        .progress-container.show { display: block; }
        .progress-bar { width: 100%; height: 30px; border: 1px solid #ccc; border-radius: 5px; overflow: hidden; background-color: #f0f0f0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #4CAF50, #45a049); width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; }
        #backupForm.processing { opacity: 0.6; pointer-events: none; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 0 auto 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
<h1>Backup — DB dump, site zip and SFTP</h1>

<?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($_POST)): ?>
    <div class="error">
        Form was submitted but you're viewing the form again. Please check the Steps below or server logs.
    </div>
<?php endif; ?>
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

<div id="progressContainer" class="progress-container">
    <div class="spinner"></div>
    <p><strong>Backup in progress...</strong></p>
    <p>This may take a few minutes depending on database and site size.</p>
    <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
    </div>
    <p id="progressText">Preparing...</p>
</div>

<form method="post" id="backupForm">
    <h2>Database</h2>
    <label>Host <input name="db_host" type="text" value="<?php echo htmlspecialchars($db_config['db_host'] ?? '127.0.0.1'); ?>"></label>
    <label>Port <input name="db_port" type="text" value="3306"></label>
    <label>User <input name="db_user" type="text" value="<?php echo htmlspecialchars($db_config['db_user'] ?? ''); ?>"></label>
    <label>Password <input name="db_pass" type="password" value="<?php echo htmlspecialchars($db_config['db_password'] ?? ''); ?>"></label>
    <label>Database name <input name="db_name" type="text" value="<?php echo htmlspecialchars($db_config['db_name'] ?? ''); ?>"></label>

    <h2>Site</h2>
    <label>Site path (absolute) <input id="site_path" name="site_path" type="text" placeholder="/var/www/html/example.com"></label>
    <div id="sitePathStatus" style="color:gray;font-size:90%;margin-top:6px;">Leave the absolute path to auto-fill DB settings from wp-config.php</div>

    <h2>SFTP target</h2>
    <label>SFTP host <input name="sftp_host" type="text"></label>
    <label>Port <input name="sftp_port" type="text" value="22"></label>
    <label>User <input name="sftp_user" type="text"></label>
    <label>Password <input name="sftp_pass" type="password"></label>
    <label>Remote directory <input name="sftp_remote" type="text" value="/backups"></label>

    <p><button type="submit" id="submitBtn">Run backup</button></p>
    <p style="color:gray">Note: this script calls `mysqldump` and requires Zip extension. For SFTP upload it prefers phpseclib (composer) or ssh2 extension.</p>
</form>

<script>
let progressFile = '';
let progressPollInterval = null;

document.getElementById('backupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const progressContainer = document.getElementById('progressContainer');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const form = document.getElementById('backupForm');
    
    // Generate unique progress file name
    const timestamp = Math.floor(Math.random() * 100000000);
    progressFile = 'backup_progress_' + timestamp + '.json';
    
    progressContainer.classList.add('show');
    form.classList.add('processing');
    
    // Start polling for progress updates
    startProgressPolling(progressFile, progressFill, progressText);
    
    // Submit form via AJAX
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        clearInterval(progressPollInterval);
        progressFill.style.width = '100%';
        document.body.innerHTML = html;
    })
    .catch(error => {
        clearInterval(progressPollInterval);
        progressText.textContent = 'Error: ' + error.message;
        form.classList.remove('processing');
    });
});

function startProgressPolling(fileName, progressFill, progressText) {
    progressPollInterval = setInterval(() => {
        fetch('progress.php?file=' + encodeURIComponent(fileName))
            .then(response => response.json())
            .then(data => {
                if (data && data.progress !== undefined) {
                    progressFill.style.width = data.progress + '%';
                    progressText.textContent = data.message || 'Processing...';
                    if (data.step) {
                        progressText.textContent += ' (' + data.step + ')';
                    }
                }
            })
            .catch(err => console.log('Progress update error (expected at start):', err.message));
    }, 500);
}

// Auto-fetch WP DB settings when user provides an absolute site path
(function(){
    const input = document.getElementById('site_path');
    const status = document.getElementById('sitePathStatus');
    if (!input) return;
    let timer = null;

    function setStatus(text, color) {
        status.textContent = text;
        if (color) status.style.color = color; else status.style.color = 'gray';
    }

    function fetchWpConfig(path) {
        setStatus('Checking wp-config.php...', 'gray');
        const formData = new FormData();
        formData.append('site_path', path);
        fetch('fetch-wp-config.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data && data.DB_NAME !== undefined) {
                    // fill fields
                    const map = {
                        'DB_HOST': 'db_host',
                        'DB_USER': 'db_user',
                        'DB_PASSWORD': 'db_pass',
                        'DB_NAME': 'db_name'
                    };
                    Object.keys(map).forEach(k => {
                        if (data[k]) {
                            const el = document.querySelector('[name="' + map[k] + '"]');
                            if (el) el.value = data[k];
                        }
                    });
                    setStatus('DB settings loaded from wp-config.php', 'green');
                } else if (data && data.error) {
                    setStatus('wp-config.php: ' + data.error, 'red');
                } else {
                    setStatus('Unexpected response from server', 'red');
                }
            })
            .catch(err => {
                setStatus('Error fetching wp-config.php: ' + err.message, 'red');
            });
    }

    input.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(() => {
            const val = input.value.trim();
            if (!val) { setStatus('Provide site path to auto-fill DB settings'); return; }
            if (val[0] !== '/') { setStatus('Please enter an absolute path starting with /', 'red'); return; }
            fetchWpConfig(val);
        }, 600);
    });
})();
</script>
</body>
</html>
