<?php
/**
 * Backup form view
 * 
 * Variables passed via extract():
 * @var \BackupApp\Service\Translator $translator Language translator
 * @var \BackupApp\Model\BackupModel $model Database model
 * @var array<string,mixed> $env Environment checks
 */
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translator->translate('title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 2rem; 
            background: linear-gradient(135deg, #d1d5db 0%, #e5e7eb 50%, #f3f4f6 100%);
            min-height: 100vh;
        }
        .card { max-width: 1100px; margin: 0 auto; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        .progress-fill { transition: width .3s; }
        
        /* Card sections - color themes only */
        .section-environment { border-left: 5px solid #16a34a; background-color: #a7f3d0; }
        .section-environment h5 { color: #15803d; }
        
        .section-paths { border-left: 5px solid #0369a1; background-color: #cffafe; }
        .section-paths h5 { color: #0c4a6e; }
        
        .section-source-db { border-left: 5px solid #ca8a04; background-color: #fef3c7; }
        .section-source-db h5 { color: #92400e; }
        
        .section-target-db { border-left: 5px solid #ca8a04; background-color: #fef3c7; }
        .section-target-db h5 { color: #92400e; }
        
        .section-sftp { border-left: 5px solid #dc2626; background-color: #fee2e2; }
        .section-sftp h5 { color: #7f1d1d; }
        
        /* SFTP row spacing */
        .row.sftp-row { margin-top: 2rem; }
    </style>
</head>
<body>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <h1 class="card-title mb-3"><?= htmlspecialchars($translator->translate('title')) ?></h1>
      <div>
        <select name="lang" class="form-select form-select-sm" onchange="changeLang(this.value)">
          <option value="cs" <?= ($translator->getLocale() === 'cs') ? 'selected' : '' ?>>cs</option>
          <option value="sk" <?= ($translator->getLocale() === 'sk') ? 'selected' : '' ?>>sk</option>
          <option value="en" <?= ($translator->getLocale() === 'en') ? 'selected' : '' ?>>en</option>
        </select>
      </div>
    </div>

    <?php if (isset($env) && is_array($env)): 
      // Cast environment array values to bool
      $env_bool = [];
      foreach ($env as $k => $v) {
        $env_bool[$k] = (bool)$v;
      }
    ?>
      <?= \BackupApp\View\Components\EnvironmentDiagnosticsComponent::render($env_bool, $translator) ?>
      
      <!-- Dry-Run Mode Toggle -->
      <div class="p-3 rounded mb-3" style="border-left: 5px solid #7c3aed; background-color: #ede9fe;">
        <h5 style="color: #6d28d9;" class="mb-3"><?= htmlspecialchars($translator->translate('dry_run_mode') ?? 'Testovací běh (bez skutečných změn)') ?></h5>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="dry_run" id="dryRunCheck" value="1">
          <label class="form-check-label" for="dryRunCheck" data-tooltip="<?= htmlspecialchars($translator->translate('dry_run_help') ?? 'Aplikace bude simulovat všechny operace bez provedení skutečných změn.') ?>">
            <strong><?= htmlspecialchars($translator->translate('dry_run_label') ?? 'Testovací běh') ?></strong>
          </label>
        </div>
        <div class="form-text mt-2" style="color: #6d28d9;">
          ℹ️ <?= htmlspecialchars($translator->translate('dry_run_help') ?? 'Aplikace bude simulovat všechny operace bez provedení skutečných změn. Ideální pro testování.') ?>
        </div>
      </div>
    <?php endif; ?>

    <form id="backupForm" method="post" enctype="multipart/form-data">
      <!-- Paths Section -->
      <div class="p-3 rounded mb-3 section-paths">
        <h5 class="mb-3"><?= htmlspecialchars($translator->translate('paths') ?? 'Cesty') ?></h5>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_source_site_path')) ?>"><?= htmlspecialchars($translator->translate('source_site_path')) ?></label>
            <input id="site_path" name="site_path" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_source_site_path')) ?>" placeholder="<?= htmlspecialchars($translator->translate('example_site_path_placeholder')) ?>" value="<?= htmlspecialchars($db_config['site_path'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_site_path')) ?>"><?= htmlspecialchars($translator->translate('target_site_path')) ?></label>
            <input id="target_site_path" name="target_site_path" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_site_path')) ?>" placeholder="<?= htmlspecialchars($translator->translate('example_target_site_placeholder')) ?>">
            <div class="form-text"><?= htmlspecialchars($translator->translate('files_will_be_copied')) ?></div>
          </div>
        </div>
      </div>

      <!-- Source & Target Database Sections - Side by side -->
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 rounded h-100 section-source-db">
            <h5><?= htmlspecialchars($translator->translate('database') ?? 'Zdrojová databáze') ?></h5>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_host')) ?>"><?= htmlspecialchars($translator->translate('host')) ?></label>
                <input name="db_host" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_host')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_host_placeholder')) ?>" value="<?= htmlspecialchars($db_config['db_host'] ?? '127.0.0.1') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_port')) ?>"><?= htmlspecialchars($translator->translate('port')) ?></label>
                <input name="db_port" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_port')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_port_placeholder')) ?>" value="3306">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_user')) ?>"><?= htmlspecialchars($translator->translate('user')) ?></label>
                <input name="db_user" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_user')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_user_placeholder')) ?>" value="<?= htmlspecialchars($db_config['db_user'] ?? '') ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_password')) ?>"><?= htmlspecialchars($translator->translate('password')) ?></label>
                <input name="db_pass" type="password" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_password')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_password_placeholder')) ?>" value="<?= htmlspecialchars($db_config['db_password'] ?? '') ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_name')) ?>"><?= htmlspecialchars($translator->translate('database_name')) ?></label>
                <input name="db_name" type="text" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_db_name')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_name_placeholder')) ?>" value="<?= htmlspecialchars($db_config['db_name'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3 rounded h-100 section-target-db">
            <h5><?= htmlspecialchars($translator->translate('target_site_db_heading')) ?></h5>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_host')) ?>"><?= htmlspecialchars($translator->translate('host')) ?></label>
                <input name="target_db_host" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_host')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_host_placeholder')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_port')) ?>"><?= htmlspecialchars($translator->translate('port')) ?></label>
                <input name="target_db_port" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_port')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_port_placeholder')) ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_user')) ?>"><?= htmlspecialchars($translator->translate('user')) ?></label>
                <input name="target_db_user" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_user')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_user_placeholder')) ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_password')) ?>"><?= htmlspecialchars($translator->translate('password')) ?></label>
                <input name="target_db_pass" type="password" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_password')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_password_placeholder')) ?>">
              </div>
              <div class="col-md-12">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_name')) ?>"><?= htmlspecialchars($translator->translate('database_name')) ?></label>
                <input name="target_db_name" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_target_db_name')) ?>" placeholder="<?= htmlspecialchars($translator->translate('db_name_placeholder')) ?>">
              </div>
            </div>
            <div class="form-text mt-3"><?= htmlspecialchars($translator->translate('target_db_section_note')) ?></div>
          </div>
        </div>
      </div>

      <!-- SFTP Section -->
      <div class="row g-3 sftp-row">
        <div class="col-12">
          <div class="p-3 rounded section-sftp">
            <h5><?= htmlspecialchars($translator->translate('sftp_section')) ?></h5>
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_host')) ?>"><?= htmlspecialchars($translator->translate('host')) ?></label>
                <input name="sftp_host" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_host')) ?>" placeholder="<?= htmlspecialchars($translator->translate('example_sftp_host')) ?>">
              </div>
              <div class="col-md-2">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_port')) ?>"><?= htmlspecialchars($translator->translate('port')) ?></label>
                <input name="sftp_port" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_port')) ?>" value="22">
              </div>
              <div class="col-md-3">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_user')) ?>"><?= htmlspecialchars($translator->translate('user')) ?></label>
                <input name="sftp_user" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_user')) ?>" placeholder="<?= htmlspecialchars($translator->translate('user')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_remote')) ?>"><?= htmlspecialchars($translator->translate('remote_dir')) ?></label>
                <input name="sftp_remote" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_remote')) ?>" placeholder="/backups" value="/backups">
              </div>
            </div>

            <div class="mt-3">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sftp_auth" id="sftpAuthPass" value="password" checked>
                <label class="form-check-label" for="sftpAuthPass"><?= htmlspecialchars($translator->translate('sftp_auth_password')) ?></label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="sftp_auth" id="sftpAuthKey" value="key">
                <label class="form-check-label" for="sftpAuthKey"><?= htmlspecialchars($translator->translate('sftp_auth_key')) ?></label>
              </div>
            </div>

            <div id="sftp-password-fields" class="mt-3">
              <input name="sftp_pass" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_password')) ?>" placeholder="<?= htmlspecialchars($translator->translate('sftp_password_placeholder')) ?>">
            </div>

            <div id="sftp-key-fields" class="mt-3" style="display:none;">
              <textarea name="sftp_key" rows="6" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_key')) ?>" placeholder="<?= htmlspecialchars($translator->translate('sftp_auth_key')) ?>"></textarea>
              <div class="mt-2"><input name="sftp_key_file" type="file" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_key')) ?>" accept=".pem,.key,text/plain"></div>
              <div class="mt-2"><input name="sftp_key_passphrase" type="password" class="form-control" data-tooltip="<?= htmlspecialchars($translator->translate('help_sftp_passphrase')) ?>" placeholder="<?= htmlspecialchars($translator->translate('key_passphrase_optional')) ?>"></div>
              <div class="form-text mt-2"><?= htmlspecialchars($translator->translate('private_key_notice')) ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Button - Centered -->
      <div class="text-center pt-3">
        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn"><?= htmlspecialchars($translator->translate('run_button')) ?></button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Localized strings for JS
 $i18n = [
   'checking_wp_config' => $translator->translate('checking_wp_config'),
   'wp_config_loaded' => $translator->translate('wp_config_loaded'),
   'wp_config_error_prefix' => $translator->translate('wp_config_error_prefix'),
   'unexpected_response' => $translator->translate('unexpected_response'),
   'error_prefix' => $translator->translate('error_prefix'),
   'provide_site_path' => $translator->translate('provide_site_path'),
   'enter_absolute_path' => $translator->translate('enter_absolute_path')
 ];
?>
<script>
const I18N = <?= json_encode($i18n, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
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
      setStatus(I18N.checking_wp_config, 'gray');
        const formData = new FormData();
        formData.append('site_path', path);
        fetch('fetch-wp-config.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data && data.DB_NAME !== undefined) {
                    const map = {'DB_HOST': 'db_host','DB_USER': 'db_user','DB_PASSWORD': 'db_pass','DB_NAME': 'db_name'};
                    Object.keys(map).forEach(k => { if (data[k]) { const el = document.querySelector('[name="' + map[k] + '"]'); if (el) el.value = data[k]; } });
                    setStatus(I18N.wp_config_loaded, 'green');
                } else if (data && data.error) {
                    setStatus(I18N.wp_config_error_prefix + data.error, 'red');
                } else {
                    setStatus(I18N.unexpected_response, 'red');
                }
            }).catch(err => setStatus(I18N.error_prefix + err.message, 'red'));
    }
    if (!input) return;
    input.addEventListener('input', function(){ clearTimeout(timer); timer = setTimeout(()=>{ const val = input.value.trim(); if (!val) { setStatus(I18N.provide_site_path); return; } if (val[0] !== '/') { setStatus(I18N.enter_absolute_path, 'red'); return; } fetchWpConfig(val); }, 600); });
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

// Initialize Bootstrap tooltips for all elements with data-tooltip attribute
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips on hover
    document.querySelectorAll('[data-tooltip]').forEach(function(el) {
        el.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            if (tooltipText) {
                // Create tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip-box';
                tooltip.textContent = tooltipText;
                tooltip.style.cssText = 'position: fixed; background: #333; color: #fff; padding: 8px 12px; border-radius: 4px; font-size: 12px; z-index: 9999; max-width: 300px; word-wrap: break-word; box-shadow: 0 2px 8px rgba(0,0,0,0.15);';
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                
                document.body.appendChild(tooltip);
                
                const removeTooltip = () => {
                    if (tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
                };
                
                el.addEventListener('mouseleave', removeTooltip, { once: true });
                el.addEventListener('click', removeTooltip, { once: true });
            }
        });
    });
});

// Preserve current URL when changing language
function changeLang(lang) {
    const url = new URL(window.location);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
}
</script>
