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
        <form method="get" class="d-flex align-items-center">
          <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm">
            <option value="cs" <?= ($translator->getLocale() === 'cs') ? 'selected' : '' ?>>cs</option>
            <option value="sk" <?= ($translator->getLocale() === 'sk') ? 'selected' : '' ?>>sk</option>
            <option value="en" <?= ($translator->getLocale() === 'en') ? 'selected' : '' ?>>en</option>
          </select>
        </form>
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
      <div class="p-3 rounded mb-4" style="border-left: 4px solid #0369a1; background-color: #cffafe;">
        <h2 class="h5 mb-3" style="color: #0c4a6e;">🔍 <?= htmlspecialchars($translator->translate('environment_diagnostics')) ?></h2>
        <div class="row g-2">
          <div class="col-md-6">
            <div class="small">
              <span class="fw-bold">mysqldump:</span>
              <span class="<?= $env['mysqldump'] ? 'text-success' : 'text-danger' ?>">
                <?= $env['mysqldump'] ? '✅ ' . htmlspecialchars($translator->translate('found')) : '❌ ' . htmlspecialchars($translator->translate('missing')) ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small">
              <span class="fw-bold">PHP Zip:</span>
              <span class="<?= $env['zip_ext'] ? 'text-success' : 'text-danger' ?>">
                <?= $env['zip_ext'] ? '✅ ' . htmlspecialchars($translator->translate('ok')) : '❌ ' . htmlspecialchars($translator->translate('missing')) ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small">
              <span class="fw-bold">phpseclib:</span>
              <span class="<?= $env['phpseclib'] ? 'text-success' : 'text-danger' ?>">
                <?= $env['phpseclib'] ? '✅ ' . htmlspecialchars($translator->translate('available')) : '❌ ' . htmlspecialchars($translator->translate('not_available')) ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small">
              <span class="fw-bold">ssh2 ext:</span>
              <span class="<?= $env['ssh2_ext'] ? 'text-success' : 'text-warning' ?>">
                <?= $env['ssh2_ext'] ? '✅ ' . htmlspecialchars($translator->translate('available')) : '⚠️ ' . htmlspecialchars($translator->translate('not_available')) ?>
              </span>
            </div>
          </div>
          <div class="col-md-6">
            <div class="small">
              <span class="fw-bold">tmp writable:</span>
              <span class="<?= $env['tmp_writable'] ? 'text-success' : 'text-danger' ?>">
                <?= $env['tmp_writable'] ? '✅ ' . htmlspecialchars($translator->translate('yes')) : '❌ ' . htmlspecialchars($translator->translate('no')) ?>
              </span>
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

    <div class="mb-4">
      <a href="./" class="btn btn-primary">← <?= htmlspecialchars($translator->translate('back')) ?></a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
