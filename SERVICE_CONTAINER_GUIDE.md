# ServiceContainer Usage Guide

**Version:** 1.0  
**Date:** February 2026  
**Location:** `src/Container/ServiceContainer.php`

---

## Quick Start

### Basic Setup

```php
use BackupApp\Container\ServiceContainer;

// Create container
$container = new ServiceContainer();

// Get services
$translator = $container->getTranslator('cs');
$model = $container->getBackupModel();
$registry = $container->get('migration_registry');
```

### Services Available

```php
// 1. Translator Service
$translator = $container->getTranslator('cs');       // Czech
$translator = $container->getTranslator('en');       // English
$translator = $container->getTranslator('sk');       // Slovak

// 2. Backup Model
$model = $container->getBackupModel();               // Without uploader
$model = $container->getBackupModel($uploader);      // With SFTP uploader

// 3. Search & Replace Service
$searchReplace = $container->get('search_replace');

// 4. Migration Registry
$registry = $container->get('migration_registry');

// 5. Configuration
$config = $container->get('config');

// 6. Directories
$logDir = $container->getLogDir();
$appRoot = $container->getAppRoot();
```

---

## Complete Examples

### Example 1: Backup Workflow

```php
use BackupApp\Container\ServiceContainer;

$container = new ServiceContainer();

// Get services
$translator = $container->getTranslator($_GET['lang'] ?? 'cs');
$model = $container->getBackupModel();

// Perform backup
$backupData = [
    'source_path' => '/var/www/html',
    'source_db' => 'wordpress_db',
    'source_db_host' => 'localhost',
    'source_db_user' => 'wp_user',
    'source_db_password' => 'password',
    'db_host' => 'localhost',
    'db_user' => 'backup_user',
    'db_password' => 'backup_pass',
    'sftp_host' => 'backup.example.com',
    'sftp_user' => 'sftp_user',
    'sftp_auth' => 'password',
    'sftp_password' => 'sftp_pass',
];

$result = $model->runBackup($backupData);

if ($result['ok']) {
    echo $translator->translate('backup_success');
} else {
    echo $translator->translate('backup_failed');
}
```

### Example 2: Migration with Registry

```php
use BackupApp\Container\ServiceContainer;

$container = new ServiceContainer();

// Get registry
$registry = $container->get('migration_registry');

// Migration data
$backupData = [
    'target_path' => '/var/www/target',
    'target_db' => 'target_db',
    'target_db_host' => 'localhost',
    'target_db_user' => 'target_user',
    'target_db_password' => 'target_pass',
];

// Execute migration steps
$steps = ['clear_caches', 'verify', 'fix_permissions', 'search_replace'];

foreach ($steps as $step) {
    if ($registry->has($step)) {
        $result = $registry->execute($step, $backupData);
        
        if (!$result['ok']) {
            error_log("Migration step failed: $step");
            break;
        }
    }
}
```

### Example 3: Database Search & Replace

```php
use BackupApp\Container\ServiceContainer;
use BackupApp\Model\DatabaseCredentials;

$container = new ServiceContainer();

// Get services
$registry = $container->get('migration_registry');

$backupData = [
    'target_path' => '/var/www/target',
    'target_db' => 'target_db',
    'target_db_host' => 'localhost',
    'target_db_user' => 'target_user',
    'target_db_password' => 'target_pass',
    'search_from' => 'http://old.example.com',
    'search_to' => 'http://new.example.com',
    'dry_run' => false,
];

$result = $registry->execute('search_replace', $backupData);

// Result structure:
// [
//     'ok' => true/false,
//     'message' => 'String message',
//     'changes' => [
//         'success' => true,
//         'tables_checked' => 10,
//         'changes_found' => 50,
//         'updates_made' => 50,
//     ]
// ]
```

### Example 4: Custom Service Registration

```php
use BackupApp\Container\ServiceContainer;

class CustomService {
    public function doSomething() {
        return "Custom work done";
    }
}

$container = new ServiceContainer();

// Register custom service
$container->set('custom', function () {
    return new CustomService();
});

// Check if service exists
if ($container->has('custom')) {
    $service = $container->get('custom');
    echo $service->doSomething();
}
```

### Example 5: Different App Root (Testing)

```php
use BackupApp\Container\ServiceContainer;

// For testing, specify custom app root
$testRoot = '/path/to/test/app';
$container = new ServiceContainer($testRoot);

// Services will use test directories
$logDir = $container->getLogDir();  // /path/to/test/app/logs
```

---

## Service Reference

### Translator Service

**Method:** `getTranslator(string $lang = 'cs'): Translator`

```php
$translator = $container->getTranslator('en');

// Usage
$message = $translator->translate('backup_success');
$translator->setLanguage('cs');
```

**Supported Languages:**
- `cs` - Czech
- `en` - English  
- `sk` - Slovak

**Fallback:** Czech (cs)

---

### Backup Model Service

**Method:** `getBackupModel(?SftpKeyUploader $uploader = null): BackupModel`

```php
// Without uploader
$model = $container->getBackupModel();

// With SFTP key uploader
$privateKey = file_get_contents('/path/to/key');
$uploader = new SftpKeyUploader($privateKey, 'passphrase');
$model = $container->getBackupModel($uploader);
```

**Available Methods:**
- `runBackup(array $data): array`
- `environmentChecks(): array`
- `clearTargetDirectory(string $path): array`
- `extractBackupArchive(string $file, string $target): array`
- `resetTargetDatabase(string $path, DatabaseCredentials $creds): array`
- `importTargetDatabase(string $path, string $dump, DatabaseCredentials $creds): array`

---

### Migration Registry Service

**Method:** `get('migration_registry'): MigrationStepRegistry`

```php
$registry = $container->get('migration_registry');

// Check if step exists
if ($registry->has('clear_caches')) {
    // Execute step
    $result = $registry->execute('clear_caches', $backupData);
}
```

**Available Steps:**
- `clear_caches` - Clear WordPress caches
- `verify` - Verify installation
- `fix_permissions` - Fix file permissions
- `search_replace` - Search & replace in database

---

### Search & Replace Service

**Method:** `get('search_replace'): SearchReplaceService`

```php
$service = $container->get('search_replace');

// Connect to database
$dbCreds = DatabaseCredentials::fromArray($data, 'db_');
$connected = $service->connectDatabase($dbCreds);

if ($connected) {
    $result = $service->searchAndReplace(
        'old.com',
        'new.com',
        [],  // all tables
        [],  // no excluded tables
        []   // no excluded columns
    );
}
```

---

### Configuration Service

**Method:** `get('config'): array`

```php
$config = $container->get('config');

// Returns WordPress configuration
// [
//     'DB_NAME' => 'wordpress_db',
//     'DB_USER' => 'wp_user',
//     'DB_PASSWORD' => 'password',
//     'DB_HOST' => 'localhost',
// ]
```

---

### Directory Information

**Methods:**
```php
$logDir = $container->getLogDir();      // Application logs directory
$appRoot = $container->getAppRoot();    // Application root directory

// Example outputs
// $logDir = '/path/to/BackUP/logs'
// $appRoot = '/path/to/BackUP'
```

---

## Common Patterns

### Pattern 1: Language-Specific Processing

```php
$container = new ServiceContainer();
$lang = $_GET['lang'] ?? 'cs';
$translator = $container->getTranslator($lang);
$model = $container->getBackupModel();

// All services use the same translator instance
$result = $model->runBackup($data);
echo $translator->translate('backup_complete');
```

### Pattern 2: SFTP Upload with Key

```php
$container = new ServiceContainer();

// Handle private key upload
if (!empty($_FILES['sftp_key'])) {
    $keyContent = file_get_contents($_FILES['sftp_key']['tmp_name']);
    $passphrase = $_POST['sftp_passphrase'] ?? null;
    $uploader = new SftpKeyUploader($keyContent, $passphrase);
    
    $model = $container->getBackupModel($uploader);
} else {
    $model = $container->getBackupModel();
}

$result = $model->runBackup($backupData);
```

### Pattern 3: Multiple Language Display

```php
$container = new ServiceContainer();

// Initial language
$translator = $container->getTranslator('cs');
$model = $container->getBackupModel();
$result = $model->runBackup($data);

// Later, user changes language
$newLang = $_GET['lang'] ?? 'en';
$newTranslator = $container->getTranslator($newLang);
echo $newTranslator->translate('backup_success');
```

### Pattern 4: Sequential Migration Steps

```php
$container = new ServiceContainer();
$registry = $container->get('migration_registry');

$steps = ['clear_caches', 'verify', 'fix_permissions', 'search_replace'];
$results = [];

foreach ($steps as $stepName) {
    if (!$registry->has($stepName)) {
        continue;
    }
    
    try {
        $result = $registry->execute($stepName, $backupData);
        $results[$stepName] = $result;
        
        if (!$result['ok']) {
            break;  // Stop on first failure
        }
    } catch (Exception $e) {
        $results[$stepName] = [
            'ok' => false,
            'message' => $e->getMessage()
        ];
        break;
    }
}

return $results;
```

### Pattern 5: Custom Services for Extensions

```php
$container = new ServiceContainer();

// Register custom service
$container->set('email_notifier', function () {
    return new EmailNotifier();
});

// Use in backup workflow
$notifier = $container->get('email_notifier');
$result = $model->runBackup($data);
$notifier->sendNotification($result);
```

---

## Error Handling

### Service Not Found

```php
$container = new ServiceContainer();

try {
    $service = $container->get('non_existent_service');
} catch (\InvalidArgumentException $e) {
    // Handle: "Service not registered: non_existent_service"
    error_log($e->getMessage());
}
```

### Checking Service Existence

```php
$container = new ServiceContainer();

if ($container->has('migration_registry')) {
    $registry = $container->get('migration_registry');
} else {
    // Service not available
}
```

---

## Best Practices

### 1. Create Container Once

```php
// ✅ Good - Create once
$container = new ServiceContainer();
$translator = $container->getTranslator('cs');
$model = $container->getBackupModel();

// ❌ Bad - Don't create multiple times
$container1 = new ServiceContainer();
$container2 = new ServiceContainer();
$container3 = new ServiceContainer();
```

### 2. Use Type Hints

```php
// ✅ Good - Type hint services
function processBackup(ServiceContainer $container): array {
    $model = $container->getBackupModel();
    return $model->runBackup($data);
}

// ❌ Bad - No type hint
function processBackup($container): array {
    // ...
}
```

### 3. Validate Data Before Service Calls

```php
// ✅ Good - Validate first
$data = array_map('trim', $_POST);
if (empty($data['source_path'])) {
    throw new Exception('Source path required');
}
$model = $container->getBackupModel();
$result = $model->runBackup($data);

// ❌ Bad - Let service fail
$result = $model->runBackup($_POST);  // May cause unclear errors
```

### 4. Use DatabaseCredentials for DB Parameters

```php
// ✅ Good - Use value object
$dbCreds = DatabaseCredentials::fromArray($data, 'db_');
$result = $model->resetTargetDatabase($path, $dbCreds);

// ❌ Bad - Multiple parameters
$result = $model->resetTargetDatabase(
    $path,
    $data['target_db'],
    $data['target_db_host'],
    $data['target_db_user'],
    $data['target_db_password']
);
```

### 5. Handle Exceptions Appropriately

```php
// ✅ Good - Comprehensive error handling
try {
    $registry = $container->get('migration_registry');
    $result = $registry->execute('search_replace', $data);
} catch (\Throwable $e) {
    error_log('Migration failed: ' . $e->getMessage());
    return ['ok' => false, 'error' => $e->getMessage()];
}

// ❌ Bad - Silent failures
$result = $registry->execute('search_replace', $data);
```

---

## Testing with ServiceContainer

### Unit Test Example

```php
use PHPUnit\Framework\TestCase;
use BackupApp\Container\ServiceContainer;

class BackupTest extends TestCase {
    private ServiceContainer $container;
    
    protected function setUp(): void {
        $this->container = new ServiceContainer('/tmp/test_app');
    }
    
    public function testBackupModelInitialization() {
        $model = $this->container->getBackupModel();
        $this->assertNotNull($model);
    }
    
    public function testTranslatorCreation() {
        $translator = $this->container->getTranslator('en');
        $this->assertNotNull($translator);
    }
}
```

### Mock Service Example

```php
$container = new ServiceContainer();

// Register mock translator
$container->set('translator', function () {
    $mock = $this->createMock(Translator::class);
    $mock->method('translate')->willReturn('Translated text');
    return $mock;
});

// Now get uses mock
$translator = $container->get('translator');
```

---

## Troubleshooting

### Issue: "Service not registered"

**Cause:** Requesting non-existent service  
**Solution:** Check service name and use `$container->has()` first

```php
if ($container->has('my_service')) {
    $service = $container->get('my_service');
}
```

### Issue: Different Service Instances

**Cause:** Creating multiple containers  
**Solution:** Reuse container instance

```php
// Pass container to methods instead of creating new one
$container = new ServiceContainer();
$result = $this->runMigration($container);

private function runMigration(ServiceContainer $container) {
    $registry = $container->get('migration_registry');
    // ...
}
```

### Issue: Language Not Applied

**Cause:** Creating translator before setting language  
**Solution:** Get translator with correct language parameter

```php
// ✅ Good
$lang = $_GET['lang'] ?? 'cs';
$translator = $container->getTranslator($lang);

// ❌ Bad
$translator = $container->getTranslator();
$translator->setLanguage($lang);  // setLanguage doesn't exist
```

---

## API Reference

### ServiceContainer Methods

```php
class ServiceContainer {
    // Constructor
    public function __construct(?string $appRoot = null)
    
    // Service access
    public function get(string $name, ...$args): mixed
    public function set(string $name, callable $factory): void
    public function has(string $name): bool
    
    // Convenience methods
    public function getTranslator(string $lang = 'cs'): Translator
    public function getBackupModel(?SftpKeyUploader $uploader = null): BackupModel
    
    // Directory info
    public function getLogDir(): string
    public function getAppRoot(): string
}
```

---

## Summary

The ServiceContainer provides:
- ✅ Centralized service management
- ✅ Easy dependency injection
- ✅ Simplified testing
- ✅ Consistent initialization
- ✅ Extensible architecture

Use it for all service access in the BackUP application.
