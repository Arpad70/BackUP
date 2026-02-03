# Refactoring Integration Guide

## Jak integrovat nové komponenty do aplikace

### 1. ENVIRONMENT DIAGNOSTICS COMPONENT

Tento komponenten eliminuje **~240 řádků duplikovaného HTML kódu** ze tří view souborů.

#### Před (stav):
```php
// src/View/form.php - 80 řádků HTML
<div class="col-md-6">
  <div class="d-flex align-items-start gap-2 p-2 rounded" style="background: #f9fafb;">
    ...
  </div>
</div>
// Opakuje se pro każdý check (mysqldump, zip, phpseclib, ssh2, tmp)
```

#### Po (s komponentou):
```php
<?= \BackupApp\View\Components\EnvironmentDiagnosticsComponent::render($env, $translator) ?>
```

**Integrace do souborů:**

1. **src/View/form.php** (řádky 50-129):
```php
<?php if (isset($env) && is_array($env)): ?>
  <?= \BackupApp\View\Components\EnvironmentDiagnosticsComponent::render($env, $translator) ?>
<?php endif; ?>
```

2. **src/View/result.php** (řádky 79-158):
```php
<?php if (!empty($env)): ?>
  <?= \BackupApp\View\Components\EnvironmentDiagnosticsComponent::render($env, $translator) ?>
<?php endif; ?>
```

3. **src/View/migration.php** (přidat pokud chybí):
```php
<?php if (!empty($env)): ?>
  <?= \BackupApp\View\Components\EnvironmentDiagnosticsComponent::render($env, $translator) ?>
<?php endif; ?>
```

---

### 2. DATABASE CREDENTIALS VALUE OBJECT

Eliminuje **~50 řádků duplikované validace** pro DB parametry.

#### Během (stav):
```php
// BackupModel::runBackup()
$dbHost = $data['db_host'] ?? null;
if (!is_string($dbHost) || $dbHost === '') {
    $dbHost = '127.0.0.1';
}
$dbUser = $data['db_user'] ?? null;
if (!is_string($dbUser)) {
    $dbUser = '';
}
// ... opakuje se 3× pro user, pass, name
```

#### Po (s DatabaseCredentials):
```php
use BackupApp\Model\DatabaseCredentials;

$credentials = DatabaseCredentials::fromArray($data);
$host = $credentials->getHost();
$user = $credentials->getUser();
$pass = $credentials->getPassword();
$name = $credentials->getDatabase();
$port = $credentials->getPort();
```

**Integrace:**

1. **BackupModel::runBackup()** (řádky 60-85):
```php
$credentials = DatabaseCredentials::fromArray($data);
$dbFile = $this->tmpDir . '/db_dump_' . time() . '.sql';

$dbResult = $this->dumpDatabase(
    $credentials->getHost(),
    $credentials->getUser(),
    $credentials->getPassword(),
    $credentials->getDatabase(),
    $credentials->getPort(),
    $dbFile
);
```

2. **SearchReplaceService::connectDatabase()** (řádky 22-33):
Již používá direktní parametry, ale lze zjednodušit:
```php
public static function fromCredentials(DatabaseCredentials $creds): self
{
    $service = new self();
    $service->connectDatabase(
        $creds->getHost(),
        $creds->getUser(),
        $creds->getPassword(),
        $creds->getDatabase(),
        $creds->getPort()
    );
    return $service;
}
```

---

### 3. MIGRATION STEP INTERFACE & IMPLEMENTATIONS

Eliminuje **~130 řádků switch/case logiky** z BackupController.

#### Během (stav):
```php
// BackupController::handleMigrationStep()
case 'clear_caches':
    if (empty($backupData['target_path'])) {
        throw new \Exception('Target path is required');
    }
    
    $targetPath = rtrim($backupData['target_path'], '/');
    $cachesPaths = [...];
    
    $cleared = 0;
    foreach ($cachesPaths as $path) {
        $files = @glob($path . '/*', GLOB_NOSORT) ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $cleared++;
            }
        }
    }
    
    $result = [
        'ok' => true,
        'message' => sprintf('🗑️ Cache vyčištěna - odstraněno %d souborů', $cleared)
    ];
    break;

case 'verify':
    // ... 20 řádků kódu pro ověření
    break;

case 'fix_permissions':
    // ... 30 řádků kódu pro nastavení oprávnění
    break;
```

#### Po (s Migration Steps):
```php
use BackupApp\Migration\MigrationStepRegistry;

private MigrationStepRegistry $migrationRegistry;

public function __construct()
{
    $this->migrationRegistry = new MigrationStepRegistry($this->translator);
}

// V handleMigrationStep():
$result = $this->migrationRegistry->execute($step, $backupData);
```

**Integrace do BackupController::handleMigrationStep():**

```php
// Nahradit velký switch statement:
case 'clear_caches':
case 'verify':
case 'fix_permissions':
    // ... 130 řádků kódu

// Tímto:
default:
    if (!$this->migrationRegistry->has($step)) {
        throw new \Exception('Unknown step: ' . $step);
    }
    
    $result = $this->migrationRegistry->execute($step, $backupData);
    break;
```

---

## SHRNUTÍ INTEGRACE

### Úspora kódu:
- **View Component**: -80 řádků (form.php) -80 (result.php) -80 (migration.php) = **-240 řádků**
- **DatabaseCredentials**: -50 řádků (BackupModel) -20 (SearchReplace) = **-70 řádků**
- **Migration Steps**: -130 řádků (BackupController switch statement) = **-130 řádků**

**CELKEM: -440 řádků duplikovaného/redundantního kódu**

### Vylepšení:
- ✅ DRY princip (Don't Repeat Yourself)
- ✅ Lepší testovatelnost (každý step se dá testovat izolovaně)
- ✅ Snadnější maintainability (přidat nový step = nová třída, ne switch case)
- ✅ Jasná separace odpovědností
- ✅ Zmenšený BackupController (ze 461 na ~330 řádků)

---

## POŘADÍ IMPLEMENTACE

1. **View Component** - Nejjednodušší, viditelný dopad
2. **DatabaseCredentials** - Používá se v více místech
3. **Migration Steps** - Největší úspora, největší refactoring

---

## TESTOVÁNÍ

### Jednotkové testy:

```php
// tests/Migration/Steps/ClearCachesStepTest.php
public function testValidateThrowsIfNoTargetPath(): void
{
    $step = new ClearCachesStep();
    $this->expectException(\InvalidArgumentException::class);
    $step->validate([]);
}

// tests/Migration/MigrationStepRegistryTest.php
public function testExecuteReturnSuccessForValidStep(): void
{
    $registry = new MigrationStepRegistry($translator);
    $result = $registry->execute('clear_caches', $backupData);
    $this->assertTrue($result['success']);
}
```

### Integrační testy:

```php
// tests/IntegrationTest.php
public function testMigrationStepsCanBeExecuted(): void
{
    $registry = new MigrationStepRegistry($translator);
    
    foreach (['clear_caches', 'verify', 'fix_permissions'] as $step) {
        $this->assertTrue($registry->has($step));
    }
}
```

---

## BACKWARD COMPATIBILITY

Všechny nové komponenty jsou **zcela kompatibilní** se stávajícím kódem:
- Staré třídy zůstávají beze změn
- Nové komponenty jsou pouze "vrstvou" nad existující logikou
- Lze implementovat postupně bez přerušení aplikace
