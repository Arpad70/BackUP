<?php
// Ensure expected variables exist to avoid undefined warnings in views/tests
if (!isset($backupData) || !is_array($backupData)) {
    $backupData = [];
}
if (!isset($env) || !is_array($env)) {
    $env = [];
}
if (!isset($translator)) {
    $translator = new \BackupApp\Service\Translator('cs', ['fallback' => 'cs']);
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($translator->translate('migration_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 2rem; 
            background: linear-gradient(135deg, #d1d5db 0%, #e5e7eb 50%, #f3f4f6 100%);
            min-height: 100vh;
        }
        .card { max-width: 1100px; margin: 0 auto; border: none; box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
        
        .migration-step {
            border-left: 5px solid #0369a1;
            background-color: #cffafe;
            position: relative;
            transition: all 0.3s ease;
        }
        .migration-step.completed {
            border-left-color: #16a34a;
            background-color: #a7f3d0;
        }
        .migration-step.processing {
            border-left-color: #ca8a04;
            background-color: #fef3c7;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0369a1;
            color: white;
            font-weight: bold;
            margin-right: 1rem;
        }
        .migration-step.completed .step-number {
            background: #16a34a;
        }
        .migration-step.processing .step-number {
            background: #ca8a04;
        }
        .method-select {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-4">
      <h1 class="card-title mb-0">🔄 <?= htmlspecialchars($translator->translate('migration_title')) ?></h1>
      <div>
        <select name="lang" class="form-select form-select-sm" onchange="changeLang(this.value)">
          <option value="cs" <?= ($translator->getLocale() === 'cs') ? 'selected' : '' ?>>cs</option>
          <option value="sk" <?= ($translator->getLocale() === 'sk') ? 'selected' : '' ?>>sk</option>
          <option value="en" <?= ($translator->getLocale() === 'en') ? 'selected' : '' ?>>en</option>
        </select>
      </div>
    </div>

    <!-- Target Path and DB Validation -->
    <?php 
    $targetPath = $backupData['target_path'] ?? '';
    $targetDb = $backupData['target_db'] ?? '';
    $sourcePath = $backupData['source_path'] ?? '';
    $sourceDb = $backupData['source_db'] ?? '';
    
    $hasErrors = false;
    $errors = [];
    
    if (empty($sourcePath) || empty($sourceDb)) {
        $hasErrors = true;
        $errors[] = $translator->translate('error_source_required');
    }
    
    if (!empty($targetPath) && empty($targetDb)) {
        $hasErrors = true;
        $errors[] = $translator->translate('migration_requires_target_db');
    }
    ?>

    <?php if ($hasErrors): ?>
      <div class="p-3 rounded mb-4" style="background-color: #fee2e2; border-left: 4px solid #dc2626;">
        <h5 class="text-danger mb-2">❌ <?= htmlspecialchars($translator->translate('errors')) ?></h5>
        <?php foreach ($errors as $error): ?>
          <div class="text-danger small">• <?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
      </div>
      <div class="mb-4 d-flex justify-content-between gap-2">
        <a href="./index.php" class="btn btn-primary">← <?= htmlspecialchars($translator->translate('back')) ?></a>
      </div>
    <?php else: ?>

    <!-- Migration Method Selection -->
    <div class="method-select p-3 rounded" style="border-left: 5px solid #16a34a; background-color: #a7f3d0;">
      <h5 style="color: #15803d;" class="mb-3">🎯 <?= htmlspecialchars($translator->translate('migration_choose_method')) ?></h5>
      <div class="btn-group w-100" role="group">
        <input type="radio" class="btn-check" name="migration_method" id="method_local" value="local" 
          <?= empty($targetPath) ? 'disabled' : '' ?> onchange="updateMigrationMethod()">
        <label class="btn btn-outline-success" for="method_local">
          🖥️ <?= htmlspecialchars($translator->translate('migration_method_local')) ?>
        </label>

        <input type="radio" class="btn-check" name="migration_method" id="method_sftp" value="sftp" 
          <?= !empty($targetPath) ? 'disabled' : '' ?> onchange="updateMigrationMethod()">
        <label class="btn btn-outline-success" for="method_sftp">
          🌐 <?= htmlspecialchars($translator->translate('migration_method_sftp')) ?>
        </label>
      </div>
      <div class="small text-muted mt-2" style="color: #15803d;">
        <?php if (empty($targetPath)): ?>
          💡 <?= htmlspecialchars($translator->translate('migration_method_sftp')) ?> <?= htmlspecialchars($translator->translate('selected')) ?>
        <?php else: ?>
          💡 <?= htmlspecialchars($translator->translate('migration_method_local')) ?> <?= htmlspecialchars($translator->translate('selected')) ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Migration Steps -->
    <div class="mt-4">
      <!-- Step 1: Clear Directory -->
      <div class="migration-step p-3 rounded mb-3" data-step="clear">
        <div class="d-flex align-items-center mb-2">
          <span class="step-number">1</span>
          <h5 class="mb-0">📁 <?= htmlspecialchars($translator->translate('migration_step_clear')) ?></h5>
        </div>
        <p class="mb-3 text-muted small">
          <?= htmlspecialchars($translator->translate('migration_preserve_backups')) ?>
        </p>
        <button class="btn btn-sm btn-outline-primary" onclick="executeMigrationStep('clear')">
          🗑️ <?= htmlspecialchars($translator->translate('migration_clear_button')) ?>
        </button>
      </div>

      <!-- Step 2: Extract Files -->
      <div class="migration-step p-3 rounded mb-3" data-step="extract">
        <div class="d-flex align-items-center mb-2">
          <span class="step-number">2</span>
          <h5 class="mb-0">📦 <?= htmlspecialchars($translator->translate('migration_step_extract')) ?></h5>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="executeMigrationStep('extract')">
          📤 <?= htmlspecialchars($translator->translate('migration_extract_button')) ?>
        </button>
      </div>

      <!-- Step 3: Reset Database -->
      <div class="migration-step p-3 rounded mb-3" data-step="reset_db">
        <div class="d-flex align-items-center mb-2">
          <span class="step-number">3</span>
          <h5 class="mb-0">🔄 <?= htmlspecialchars($translator->translate('migration_step_reset_db')) ?></h5>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="executeMigrationStep('reset_db')">
          🔧 Reset
        </button>
      </div>

      <!-- Step 4: Import Database -->
      <div class="migration-step p-3 rounded mb-3" data-step="import_db">
        <div class="d-flex align-items-center mb-2">
          <span class="step-number">4</span>
          <h5 class="mb-0">💾 <?= htmlspecialchars($translator->translate('migration_step_import_db')) ?></h5>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="executeMigrationStep('import_db')">
          📥 <?= htmlspecialchars($translator->translate('migration_import_db_button')) ?>
        </button>
      </div>
    </div>

    <!-- Status Output -->
    <div id="status-output" class="p-3 rounded bg-light mt-4" style="display: none; min-height: 150px;">
      <pre id="status-content" style="margin: 0; font-size: 0.85rem; max-height: 400px; overflow-y: auto;"></pre>
    </div>

    <!-- Navigation -->
    <div class="mb-4 d-flex justify-content-between gap-2 mt-4">
      <a href="./" class="btn btn-primary">← <?= htmlspecialchars($translator->translate('back')) ?></a>
      <button class="btn btn-success" onclick="completeMigration()" disabled id="complete-btn">
        ✓ <?= htmlspecialchars($translator->translate('complete')) ?>
      </button>
    </div>

    <?php endif; ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Store backup data
const backupData = <?= json_encode($backupData) ?>;
const completedSteps = new Set();

function changeLang(lang) {
    const url = new URL(window.location);
    url.searchParams.set('lang', lang);
    window.location.href = url.toString();
}

function updateMigrationMethod() {
    const method = document.querySelector('input[name="migration_method"]:checked')?.value;
    console.log('Migration method:', method);
}

function executeMigrationStep(step) {
    const statusOutput = document.getElementById('status-output');
    const statusContent = document.getElementById('status-content');
    const stepElement = document.querySelector(`[data-step="${step}"]`);
    
    statusOutput.style.display = 'block';
    statusContent.textContent = 'Spouštění kroku: ' + step + '...';
    
    if (stepElement) {
        stepElement.classList.remove('completed');
        stepElement.classList.add('processing');
    }
    
    const method = document.querySelector('input[name="migration_method"]:checked')?.value || 'local';
    
    fetch('./index.php?action=migration_step', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            step: step,
            backupData: backupData,
            method: method
        }),
        credentials: 'include'
    })
    .then(r => r.json())
    .then(data => {
        const output = data.output || (data.result?.message) || 'Krok dokončen';
        statusContent.textContent = output;
        
        if (data.success) {
            completedSteps.add(step);
            if (stepElement) {
                stepElement.classList.remove('processing');
                stepElement.classList.add('completed');
            }
            // Update complete button status
            updateCompleteButton();
        } else {
            if (stepElement) {
                stepElement.classList.remove('processing');
            }
            statusContent.textContent = 'Chyba: ' + (data.error || 'Neznámá chyba');
        }
    })
    .catch(err => {
        statusContent.textContent = 'Chyba: ' + err.message;
        if (stepElement) {
            stepElement.classList.remove('processing');
        }
    });
}

function updateCompleteButton() {
    // Enable complete button if all steps are done (or optional)
    const completeBtn = document.getElementById('complete-btn');
    if (completeBtn) {
        completeBtn.disabled = false;
    }
}

function completeMigration() {
    alert('Migrace byla úspěšně dokončena!');
    window.location.href = './';
}
</script>
</body>
</html>
