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
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translator->translate('result_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 2rem; 
            background: linear-gradient(135deg, #d1d5db 0%, #e5e7eb 50%, #f3f4f6 100%);
            min-height: 100vh;
        }
        .card { max-width: 1100px; margin: 0 auto; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        
        /* Card sections - color themes only */
        .section-environment { border-left: 5px solid #16a34a; background-color: #a7f3d0; }
        .section-environment h5 { color: #15803d; }
        
        .step-item {
            border-left: 4px solid #0369a1;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .step-item.success {
            border-left-color: #16a34a;
        }
        .step-item.failed {
            border-left-color: #dc2626;
        }
        .error-card {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
        }
        .message-text {
            color: #666;
            font-size: 0.95rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <h1 class="card-title mb-0">📋 <?= htmlspecialchars($translator->translate('result_title')) ?></h1>
      <div>
        <select name="lang" class="form-select form-select-sm" onchange="changeLang(this.value)">
          <option value="cs" <?= ($translator->getLocale() === 'cs') ? 'selected' : '' ?>>cs</option>
          <option value="sk" <?= ($translator->getLocale() === 'sk') ? 'selected' : '' ?>>sk</option>
          <option value="en" <?= ($translator->getLocale() === 'en') ? 'selected' : '' ?>>en</option>
        </select>
      </div>
    </div>
    
    <?php if (!empty($result['errors'])): ?>
      <div class="p-3 rounded mb-4 error-card">
        <h2 class="h5 text-danger mb-3">❌ <?= htmlspecialchars($translator->translate('errors')) ?></h2>
        <?php foreach($result['errors'] as $e): ?>
          <div class="message-text mb-2">
            • <?= htmlspecialchars($e) ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($result['warnings'])): ?>
      <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <strong>⚠️ <?= htmlspecialchars($translator->translate('warnings')) ?></strong>
        <?php foreach($result['warnings'] as $w): ?>
          <div class="message-text"><?= htmlspecialchars($w) ?></div>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
    
    <?php if (!empty($env)): ?>
      <div class="p-3 rounded mb-3 section-environment">
        <h5 class="mb-3"><?= htmlspecialchars($translator->translate('environment_diagnostics')) ?></h5>
        <div class="row g-3">
          <!-- mysqldump -->
          <div class="col-md-6">
            <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
              <span style="font-size: 1.5rem; min-width: 2rem;">
                <?= $env['mysqldump'] ? '✅' : '❌' ?>
              </span>
              <div style="flex: 1;">
                <strong data-tooltip="<?= htmlspecialchars($translator->translate('env_mysqldump_desc')) ?>"><?= htmlspecialchars($translator->translate('env_mysqldump')) ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($translator->translate('env_status_required')) ?></div>
                <span class="badge <?= $env['mysqldump'] ? 'bg-success' : 'bg-danger' ?> mt-2">
                  <?= htmlspecialchars($translator->translate($env['mysqldump'] ? 'ok' : 'missing')) ?>
                </span>
              </div>
            </div>
          </div>
          
          <!-- zip ext -->
          <div class="col-md-6">
            <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
              <span style="font-size: 1.5rem; min-width: 2rem;">
                <?= $env['zip_ext'] ? '✅' : '❌' ?>
              </span>
              <div style="flex: 1;">
                <strong data-tooltip="<?= htmlspecialchars($translator->translate('env_zip_desc')) ?>"><?= htmlspecialchars($translator->translate('env_zip')) ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($translator->translate('env_status_required')) ?></div>
                <span class="badge <?= $env['zip_ext'] ? 'bg-success' : 'bg-danger' ?> mt-2">
                  <?= htmlspecialchars($translator->translate($env['zip_ext'] ? 'ok' : 'missing')) ?>
                </span>
              </div>
            </div>
          </div>
          
          <!-- phpseclib -->
          <div class="col-md-6">
            <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
              <span style="font-size: 1.5rem; min-width: 2rem;">
                <?= $env['phpseclib'] ? '✅' : '⚠️' ?>
              </span>
              <div style="flex: 1;">
                <strong data-tooltip="<?= htmlspecialchars($translator->translate('env_phpseclib_desc')) ?>"><?= htmlspecialchars($translator->translate('env_phpseclib')) ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($translator->translate('env_status_recommended')) ?></div>
                <span class="badge <?= $env['phpseclib'] ? 'bg-success' : 'bg-warning' ?> mt-2">
                  <?= htmlspecialchars($translator->translate($env['phpseclib'] ? 'available' : 'not_available')) ?>
                </span>
              </div>
            </div>
          </div>
          
          <!-- ssh2 ext -->
          <div class="col-md-6">
            <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
              <span style="font-size: 1.5rem; min-width: 2rem;">
                <?= $env['ssh2_ext'] ? '✅' : '⚠️' ?>
              </span>
              <div style="flex: 1;">
                <strong data-tooltip="<?= htmlspecialchars($translator->translate('env_ssh2_desc')) ?>"><?= htmlspecialchars($translator->translate('env_ssh2')) ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($translator->translate('env_status_recommended')) ?></div>
                <span class="badge <?= $env['ssh2_ext'] ? 'bg-success' : 'bg-warning' ?> mt-2">
                  <?= htmlspecialchars($translator->translate($env['ssh2_ext'] ? 'available' : 'not_available')) ?>
                </span>
              </div>
            </div>
          </div>
          
          <!-- tmp writable -->
          <div class="col-md-6">
            <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
              <span style="font-size: 1.5rem; min-width: 2rem;">
                <?= $env['tmp_writable'] ? '✅' : '❌' ?>
              </span>
              <div style="flex: 1;">
                <strong data-tooltip="<?= htmlspecialchars($translator->translate('env_tmp_writable_desc')) ?>"><?= htmlspecialchars($translator->translate('env_tmp_writable')) ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($translator->translate('env_status_required')) ?></div>
                <span class="badge <?= $env['tmp_writable'] ? 'bg-success' : 'bg-danger' ?> mt-2">
                  <?= htmlspecialchars($translator->translate($env['tmp_writable'] ? 'yes' : 'no')) ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
    
    <div class="p-3 rounded mb-4">
      <h2 class="h5 mb-3">📝 <?= htmlspecialchars($translator->translate('steps')) ?></h2>
      <?php if (empty($result['steps'])): ?>
        <div class="text-muted">No steps executed.</div>
      <?php else: ?>
        <?php foreach($result['steps'] as $idx => $s): ?>
          <?php 
            $hasError = false;
            foreach ($s as $key => $val) {
              if (is_array($val) && !empty($val['ok']) === false) {
                $hasError = true;
                break;
              }
            }
          ?>
          <div class="step-item <?= $hasError ? 'failed' : 'success' ?>">
            <div class="small fw-bold">
              <?php echo ($idx + 1) . '. '; ?>
              <?php
                // Print step name (first key)
                $keys = array_keys($s);
                if (!empty($keys)) {
                  echo htmlspecialchars($keys[0]);
                }
              ?>
              <?php
                // Print first value as status
                foreach ($s as $key => $val) {
                  if (is_array($val)) {
                    $status = !empty($val['ok']) ? '✅ ' . htmlspecialchars($translator->translate('ok')) : '❌ ' . htmlspecialchars($translator->translate('failed'));
                    echo ' — ' . $status;
                    if (!empty($val['message'])) {
                      echo '<div class="message-text">' . htmlspecialchars($val['message']) . '</div>';
                    }
                  } else if (!is_bool($val)) {
                    echo '<div class="message-text text-muted">📂 ' . htmlspecialchars($val) . '</div>';
                  }
                  break; // Only show first item in step
                }
              ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    
    <?php if (!empty($appLog)): ?>
      <div class="p-3 rounded mb-4">
        <h2 class="h5 mb-3">📋 <?= htmlspecialchars($translator->translate('application_log')) ?></h2>
        <pre style="background:#f8f8f8;padding:12px;border-left:4px solid #0369a1;border-radius:4px;max-height:400px;overflow-y:auto;font-size:0.85rem;white-space:pre-wrap;word-break:break-word;margin:0;"><?php echo htmlspecialchars($appLog); ?></pre>
      </div>
    <?php endif; ?>

    <a href="./" class="btn btn-primary">← <?= htmlspecialchars($translator->translate('back')) ?></a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
</body>
</html>
