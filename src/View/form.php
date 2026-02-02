<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translator->translate('title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 2rem; }
        .card { max-width: 1100px; margin: 0 auto; }
        .progress-fill { transition: width .3s; }
    </style>
</head>
<body>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <h1 class="card-title mb-3"><?= htmlspecialchars($translator->translate('title')) ?></h1>
      <div>
        <form method="get" class="d-flex align-items-center">
          <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm">
            <option value="cs" <?= ($translator->getLocale() === 'cs') ? 'selected' : '' ?>>cs</option>
            <option value="sk" <?= ($translator->getLocale() === 'sk') ? 'selected' : '' ?>>sk</option>
            <option value="en" <?= ($translator->getLocale() === 'en') ? 'selected' : '' ?>>en</option>
          </select>
        </form>
      </div>
    </div>

    <?php if (isset($env) && is_array($env)): ?>
      <div class="mb-3">
        <div class="d-flex gap-3 flex-wrap">
          <div class="badge bg-<?= $env['mysqldump'] ? 'success' : 'danger' ?>">mysqldump: <?= $env['mysqldump'] ? 'OK' : 'missing' ?></div>
          <div class="badge bg-<?= $env['zip_ext'] ? 'success' : 'danger' ?>">zip ext: <?= $env['zip_ext'] ? 'OK' : 'missing' ?></div>
          <div class="badge bg-<?= $env['phpseclib'] ? 'success' : 'warning' ?>">phpseclib: <?= $env['phpseclib'] ? 'available' : 'not installed' ?></div>
          <div class="badge bg-<?= $env['ssh2_ext'] ? 'success' : 'warning' ?>">ssh2: <?= $env['ssh2_ext'] ? 'available' : 'not available' ?></div>
          <div class="badge bg-<?= $env['tmp_writable'] ? 'success' : 'danger' ?>">tmp writable: <?= $env['tmp_writable'] ? 'yes' : 'no' ?></div>
        </div>
      </div>
    <?php endif; ?>

    <form id="backupForm" method="post" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label"><?= htmlspecialchars($translator->translate('source_site_path')) ?></label>
          <input id="site_path" name="site_path" type="text" class="form-control" placeholder="/var/www/html/example.com" value="<?= htmlspecialchars($db_config['site_path'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= htmlspecialchars($translator->translate('target_site_path')) ?></label>
          <input id="target_site_path" name="target_site_path" type="text" class="form-control" placeholder="/var/www/html/example-target.com">
          <div class="form-text">If provided, files will be copied locally from source to target (no SFTP needed).</div>
        </div>
        <div class="col-12">
          <h5 class="mt-3">Target site database (optional)</h5>
          <div class="row g-2">
            <div class="col-md-3"><input name="target_db_host" class="form-control" placeholder="DB host"></div>
            <div class="col-md-1"><input name="target_db_port" class="form-control" placeholder="3306"></div>
            <div class="col-md-3"><input name="target_db_user" class="form-control" placeholder="DB user"></div>
            <div class="col-md-3"><input name="target_db_name" class="form-control" placeholder="DB name"></div>
            <div class="col-md-2"><input name="target_db_pass" type="password" class="form-control" placeholder="DB password"></div>
          </div>
          <div class="form-text"><?= htmlspecialchars($translator->translate('target_db_section_note')) ?></div>
        </div>

        <div class="col-12">
          <h5 class="mt-3"><?= htmlspecialchars($translator->translate('database')) ?></h5>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($translator->translate('host')) ?></label>
              <input name="db_host" type="text" class="form-control" value="<?= htmlspecialchars($db_config['db_host'] ?? '127.0.0.1') ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label"><?= htmlspecialchars($translator->translate('port')) ?></label>
              <input name="db_port" type="text" class="form-control" value="3306">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= htmlspecialchars($translator->translate('user')) ?></label>
              <input name="db_user" type="text" class="form-control" value="<?= htmlspecialchars($db_config['db_user'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($translator->translate('password')) ?></label>
              <input name="db_pass" type="password" class="form-control" value="<?= htmlspecialchars($db_config['db_password'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label"><?= htmlspecialchars($translator->translate('database_name')) ?></label>
              <input name="db_name" type="text" class="form-control" value="<?= htmlspecialchars($db_config['db_name'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="col-12">
          <h5 class="mt-3"><?= htmlspecialchars($translator->translate('sftp_section')) ?></h5>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label"><?= htmlspecialchars($translator->translate('host')) ?></label>
              <input name="sftp_host" class="form-control" placeholder="sftp.example.com">
            </div>
            <div class="col-md-2">
              <label class="form-label"><?= htmlspecialchars($translator->translate('port')) ?></label>
              <input name="sftp_port" class="form-control" value="22">
            </div>
            <div class="col-md-3">
              <label class="form-label"><?= htmlspecialchars($translator->translate('user')) ?></label>
              <input name="sftp_user" class="form-control" placeholder="user">
            </div>
            <div class="col-md-3">
              <label class="form-label">Remote dir</label>
              <input name="sftp_remote" class="form-control" placeholder="/backups" value="/backups">
            </div>
          </div>

          <div class="mt-2">
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="sftp_auth" id="sftpAuthPass" value="password" checked>
              <label class="form-check-label" for="sftpAuthPass"><?= htmlspecialchars($translator->translate('sftp_auth_password')) ?></label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="sftp_auth" id="sftpAuthKey" value="key">
              <label class="form-check-label" for="sftpAuthKey"><?= htmlspecialchars($translator->translate('sftp_auth_key')) ?></label>
            </div>
          </div>

          <div id="sftp-password-fields" class="mt-2">
            <input name="sftp_pass" class="form-control" placeholder="<?= htmlspecialchars($translator->translate('sftp_password_placeholder')) ?>">
          </div>

          <div id="sftp-key-fields" class="mt-2" style="display:none;">
            <textarea name="sftp_key" rows="6" class="form-control" placeholder="Paste private key here"></textarea>
            <div class="mt-2"><input name="sftp_key_file" type="file" class="form-control" accept=".pem,.key,text/plain"></div>
            <div class="mt-2"><input name="sftp_key_passphrase" type="password" class="form-control" placeholder="Key passphrase (optional)"></div>
            <div class="form-text mt-1"><?= htmlspecialchars($translator->translate('private_key_notice')) ?></div>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-end mt-3">
          <button type="submit" class="btn btn-primary btn-lg" id="submitBtn"><?= htmlspecialchars($translator->translate('run_button')) ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle SFTP auth fields
document.querySelectorAll('input[name="sftp_auth"]').forEach(function(r){
    r.addEventListener('change', function(){
        var mode = document.querySelector('input[name="sftp_auth"]:checked').value;
        document.getElementById('sftp-password-fields').style.display = (mode === 'password') ? 'block' : 'none';
        document.getElementById('sftp-key-fields').style.display = (mode === 'key') ? 'block' : 'none';
    });
});

// If target path provided, hide SFTP section
var targetInput = document.getElementById('target_site_path');
if (targetInput) {
    targetInput.addEventListener('input', function(){
        var val = this.value.trim();
        var sftpSection = document.querySelector('h5.mt-3 + div');
        if (val !== '') {
            // hide sftp fields
            document.querySelectorAll('[name^="sftp_"]').forEach(function(el){ if(el.closest) el.closest('div').style.display='none'; });
        } else {
            document.querySelectorAll('[name^="sftp_"]').forEach(function(el){ if(el.closest) el.closest('div').style.display='block'; });
        }
    });
}

// Auto-fetch WP DB settings when user provides an absolute site path
(function(){
    const input = document.getElementById('site_path');
    const statusEl = document.createElement('div');
    input.parentNode.appendChild(statusEl);
    let timer = null;
    function setStatus(text, color) { statusEl.textContent = text; statusEl.style.color = color || 'gray'; }
    function fetchWpConfig(path) {
        setStatus('Checking wp-config.php...', 'gray');
        const formData = new FormData();
        formData.append('site_path', path);
        fetch('fetch-wp-config.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data && data.DB_NAME !== undefined) {
                    const map = {'DB_HOST': 'db_host','DB_USER': 'db_user','DB_PASSWORD': 'db_pass','DB_NAME': 'db_name'};
                    Object.keys(map).forEach(k => { if (data[k]) { const el = document.querySelector('[name="' + map[k] + '"]'); if (el) el.value = data[k]; } });
                    setStatus('DB settings loaded from wp-config.php', 'green');
                } else if (data && data.error) {
                    setStatus('wp-config.php: ' + data.error, 'red');
                } else {
                    setStatus('Unexpected response from server', 'red');
                }
            }).catch(err => setStatus('Error: ' + err.message, 'red'));
    }
    if (!input) return;
    input.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(()=>{ const val = input.value.trim(); if (!val) { setStatus('Provide site path to auto-fill DB settings'); return; } if (val[0] !== '/') { setStatus('Please enter an absolute path starting with /', 'red'); return; } fetchWpConfig(val); }, 600); });
})();
</script>
</body>
</html>

<script>
// Toggle SFTP auth fields
document.querySelectorAll('input[name="sftp_auth"]').forEach(function(r){
    r.addEventListener('change', function(){
        var mode = document.querySelector('input[name="sftp_auth"]:checked').value;
        document.getElementById('sftp-password-fields').style.display = (mode === 'password') ? 'block' : 'none';
        document.getElementById('sftp-key-fields').style.display = (mode === 'key') ? 'block' : 'none';
    });
});
</script>
