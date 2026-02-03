# BackUP Architecture Overview

**Version:** 2.0 (Post-Refactoring)  
**Last Updated:** February 2026  
**Status:** Stable & Production Ready

---

## Table of Contents
1. [Architecture Layers](#architecture-layers)
2. [Component Relationships](#component-relationships)
3. [Design Patterns](#design-patterns)
4. [Data Flow](#data-flow)
5. [Service Dependencies](#service-dependencies)

---

## Architecture Layers

### 1. Entry Point Layer

**Location:** `public/index.php`  
**Responsibility:** HTTP request routing

```
HTTP Request
    ↓
index.php (Bootstrap)
    ↓
BackupController::handle()
```

### 2. Controller Layer

**File:** `src/Controller/BackupController.php` (305 lines)

**Responsibilities:**
- HTTP request handling
- Service initialization via ServiceContainer
- SFTP key validation and handling
- Session management
- View routing

**Key Methods:**
```php
public function handle(): void
    - Main request dispatcher
    - Language and session setup
    - Form, migration, and AJAX request routing

private function handleMigrationStep(ServiceContainer $container): void
    - AJAX request handler for migration steps
    - Uses MigrationStepRegistry
    - Returns JSON responses
```

### 3. Container Layer (Dependency Injection)

**File:** `src/Container/ServiceContainer.php` (175 lines)

**Responsibility:** Centralized service instantiation and lifecycle management

**Managed Services:**
```php
translator        → Service\Translator
config            → Config (WordPress configuration)
backup_model      → Model\BackupModel
search_replace    → Service\SearchReplaceService
migration_registry → Migration\MigrationStepRegistry
```

**Usage Pattern:**
```php
$container = new ServiceContainer();
$translator = $container->getTranslator('cs');
$model = $container->getBackupModel();
$registry = $container->get('migration_registry');
```

### 4. Business Logic Layer

#### 4.1 Models

**BackupModel** (`src/Model/BackupModel.php` - 576 lines)
```
Responsibilities:
├── orchestrate backup operations
├── database dumping (mysqldump)
├── compression (ZIP)
├── SFTP upload
├── target directory operations
├── database operations (reset, import)
└── environment checking
```

**DatabaseCredentials** (`src/Model/DatabaseCredentials.php` - 197 lines)
```
Responsibilities:
├── encapsulate DB connection parameters
├── validation logic centralization
├── factory methods for various sources
├── type-safe parameter access
└── immutability guarantee
```

#### 4.2 Services

**SearchReplaceService** (`src/Service/SearchReplaceService.php` - 401 lines)
```
Responsibilities:
├── database connection management
├── search and replace operations
├── table enumeration
├── dry-run mode support
└── detailed reporting
```

**DatabaseDumper** (`src/Service/DatabaseDumper.php` - 54 lines)
```
Responsibilities:
├── mysqldump wrapper
├── command execution
└── error handling
```

**SftpUploader** (`src/Service/SftpUploader.php` - 85 lines)
```
Responsibilities:
├── SFTP connection management
├── file upload operations
└── connection cleanup
```

**SftpKeyUploader** (`src/Service/SftpKeyUploader.php` - 71 lines)
```
Responsibilities:
├── private key handling
├── passphrase support
└── secure key in-memory processing
```

**Translator** (`src/Service/Translator.php` - 58 lines)
```
Responsibilities:
├── multi-language support
├── translation lookup
├── fallback language handling
└── dynamic language switching
```

#### 4.3 Migration System

**MigrationStepInterface** (`src/Migration/MigrationStepInterface.php` - 54 lines)
```php
interface MigrationStepInterface {
    public function execute(array $data): array;
    public function validate(array $data): array;
}
```

**MigrationStepRegistry** (`src/Migration/MigrationStepRegistry.php` - 149 lines)
```
Pattern: Registry + Strategy Pattern
Responsibilities:
├── step registration
├── step execution dispatch
├── error handling
└── translation integration
```

**Registered Steps:**
```php
'clear_caches'        → ClearCachesStep
'verify'              → VerifyStep
'fix_permissions'     → FixPermissionsStep
'search_replace'      → SearchReplaceStep
```

**Migration Steps:**
```
ClearCachesStep (103 lines)
├── WordPress cache clearing
└── Plugin cache handling

VerifyStep (107 lines)
├── WordPress installation check
├── Database connectivity
└── Configuration validation

FixPermissionsStep (136 lines)
├── File permission verification
├── Directory permission fixing
└── WordPress standard compliance

SearchReplaceStep (137 lines)
├── Search/replace execution
├── Database credential handling
└── Dry-run support
```

### 5. View Layer

**Files:**
- `src/View/form.php` (306 lines) - Backup form
- `src/View/migration.php` (391 lines) - Migration wizard
- `src/View/result.php` (208 lines) - Backup results
- `src/View/Components/EnvironmentDiagnosticsComponent.php` - Reusable component

**Components:**
```
EnvironmentDiagnosticsComponent
├── System diagnostics rendering
├── Reusable across 3 views
└── No code duplication
```

### 6. Contract Layer

**UploaderInterface** (`src/Contract/UploaderInterface.php`)
```php
interface UploaderInterface {
    public function upload(string $localPath, string $remotePath): bool;
    public function connect(): bool;
}
```

---

## Component Relationships

### Dependency Graph

```
BackupController
    ├── uses → ServiceContainer
    │           ├── creates → Translator
    │           ├── creates → BackupModel
    │           │             ├── uses → DatabaseCredentials (validation)
    │           │             ├── uses → SftpUploader
    │           │             ├── uses → DatabaseDumper
    │           │             └── uses → Translator
    │           ├── creates → MigrationStepRegistry
    │           │             ├── contains → ClearCachesStep
    │           │             ├── contains → VerifyStep
    │           │             ├── contains → FixPermissionsStep
    │           │             └── contains → SearchReplaceStep
    │           │                           ├── uses → SearchReplaceService
    │           │                           └── uses → DatabaseCredentials
    │           └── creates → SearchReplaceService
    │
    ├── includes → View files
    │             └── include → EnvironmentDiagnosticsComponent
    │
    └── handles → HTTP requests
                  ├── POST → Backup execution
                  └── AJAX → Migration steps

DatabaseCredentials
    ├── created by → BackupModel::runBackup()
    ├── created by → SearchReplaceStep::execute()
    ├── created by → BackupController (for reset_db, import_db)
    └── used in → SearchReplaceService::connectDatabase()
```

---

## Design Patterns

### 1. Service Locator Pattern (ServiceContainer)

**Purpose:** Centralize service creation and management

**Implementation:**
```php
class ServiceContainer {
    private array $services = [];
    private array $factories = [];
    
    public function get(string $name, ...$args): mixed {
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }
        return $this->services[$name] = $this->factories[$name](...$args);
    }
    
    public function set(string $name, callable $factory): void {
        $this->factories[$name] = $factory;
    }
}
```

**Usage:**
```php
$container = new ServiceContainer();
$translator = $container->getTranslator('cs');
$model = $container->getBackupModel();
```

### 2. Registry Pattern (MigrationStepRegistry)

**Purpose:** Manage dynamic registration and execution of strategies

**Implementation:**
```php
class MigrationStepRegistry {
    private array $steps = [];
    
    public function register(string $name, string $className): void {
        $this->steps[$name] = $className;
    }
    
    public function execute(string $name, array $data): array {
        $step = new $this->steps[$name](...);
        return $step->execute($data);
    }
}
```

**Benefits:**
- Replaces switch statements
- Extensible without modifying registry
- Easy to test

### 3. Strategy Pattern (MigrationStepInterface)

**Purpose:** Define family of migration algorithms

**Implementation:**
```php
interface MigrationStepInterface {
    public function execute(array $data): array;
    public function validate(array $data): array;
}

class ClearCachesStep implements MigrationStepInterface { ... }
class VerifyStep implements MigrationStepInterface { ... }
class FixPermissionsStep implements MigrationStepInterface { ... }
class SearchReplaceStep implements MigrationStepInterface { ... }
```

**Benefits:**
- Interchangeable algorithms
- Easy to add new steps
- Testable in isolation

### 4. Value Object Pattern (DatabaseCredentials)

**Purpose:** Encapsulate related data with validation

**Implementation:**
```php
class DatabaseCredentials {
    private string $host;
    private string $user;
    private string $password;
    private string $database;
    private int $port;
    
    public static function fromArray(array $data, string $prefix): self { ... }
    public function validate(): array { ... }
    public function getHost(): string { ... }
}
```

**Benefits:**
- Type safety
- Validation centralization
- Immutability
- Self-documenting code

### 5. Component Pattern (EnvironmentDiagnosticsComponent)

**Purpose:** Reusable view component

**Implementation:**
```php
class EnvironmentDiagnosticsComponent {
    public function __construct(array $env) { ... }
    public function render(): string { ... }
}
```

**Usage:**
```php
$component = new EnvironmentDiagnosticsComponent($env);
echo $component->render();
```

**Benefits:**
- DRY principle
- Consistency across views
- Easier maintenance

---

## Data Flow

### Backup Operation Flow

```
HTTP POST Request
    ↓
BackupController::handle()
    ├─ Validate form input
    ├─ Handle private key (if SFTP)
    ├─ ServiceContainer->getBackupModel()
    └─ BackupModel->runBackup($data)
        ├─ DatabaseCredentials::fromArray()
        ├─ dumpDatabase()
        │   └─ DatabaseDumper->dump()
        ├─ createBackupArchive()
        └─ uploadBackupArchive()
            └─ SftpUploader->upload() or local copy
    ↓
Store result in $_SESSION
    ↓
Include result.php view
    ├─ EnvironmentDiagnosticsComponent->render()
    └─ Display results
```

### Migration Operation Flow

```
AJAX Request (XMLHttpRequest)
    ↓
BackupController::handleMigrationStep()
    ├─ ServiceContainer->get('migration_registry')
    └─ MigrationStepRegistry->execute($step, $data)
        └─ Specific Step Implementation
            ├─ execute($data)
            │   └─ Business logic (caches, verify, permissions, search-replace)
            └─ Return JSON result
    ↓
JavaScript receives JSON
    ↓
Update UI with results
    ↓
Continue to next step
```

### Database Search & Replace Flow

```
SearchReplaceStep::execute()
    ├─ DatabaseCredentials::fromTargetArray($data)
    ├─ SearchReplaceService->connectDatabase($dbCredentials)
    ├─ SearchReplaceService->searchAndReplace()
    │   ├─ Get all tables
    │   ├─ For each table:
    │   │   ├─ Get columns
    │   │   └─ Execute replace query
    │   └─ Build report
    └─ Return results
```

---

## Service Dependencies

### Initialization Order

```
1. ServiceContainer created
   ↓
2. Translator loaded (language from GET/COOKIE)
   ↓
3. Config (WordPress configuration)
   ↓
4. BackupModel instantiated
   ↓
5. MigrationStepRegistry created
   ├─ Loads ClearCachesStep
   ├─ Loads VerifyStep
   ├─ Loads FixPermissionsStep
   └─ Loads SearchReplaceStep
   ↓
6. SearchReplaceService created (on demand)
```

### Dependency Injection Points

```
BackupController
    → receives services from ServiceContainer
    
BackupModel
    → receives Translator
    → receives optional SftpUploader
    
MigrationStepRegistry
    → receives Translator
    → creates steps via reflection
    
MigrationSteps
    → receive Translator (from registry)
    → receive SearchReplaceService (SearchReplaceStep)
    
SearchReplaceService
    → receives Translator
```

---

## Configuration

### Environment Detection

```php
$env = $model->environmentChecks();
// Returns:
[
    'php_version' => '',
    'wp_cli_installed' => false,
    'os_type' => '',
    'sftp_available' => false,
    'mysql_available' => false,
    'writable_dirs' => [],
    'server_info' => ''
]
```

### Language Support

```php
// Supported languages
$translator = $container->getTranslator('cs');  // Czech
$translator = $container->getTranslator('en');  // English
$translator = $container->getTranslator('sk');  // Slovak
```

### Database Configuration

```php
// WordPress configuration loading
$dbConfig = Config::loadWordPressConfig();
// Reads from wp-config.php
```

---

## Error Handling

### Exception Hierarchy

```
\Throwable
├── \Exception
│   ├── \InvalidArgumentException (DatabaseCredentials validation)
│   └── \RuntimeException (Service execution failures)
└── \Error
```

### Error Recovery

```
BackupController::handle()
    try {
        // All operations
    } catch (\Throwable $e) {
        // Log error
        // Display error page
        // Return HTTP 500
    }
```

---

## Security Considerations

### 1. Private Key Handling
- In-memory only (not stored)
- Maximum 16 KB size limit
- Validates PEM/OpenSSH format
- Immediately cleared from POST data

### 2. Database Parameters
- Validated via DatabaseCredentials
- Not stored in $_SESSION
- Used only during execution
- Shell-escaped in commands

### 3. File Operations
- Uploaded files deleted immediately
- Paths validated
- Permissions checked

---

## Performance Characteristics

### ServiceContainer Overhead
- ~1ms for instantiation
- Minimal memory footprint
- Lazy loading support

### Migration Steps
- ClearCaches: ~100-500ms (depends on cache size)
- Verify: ~50-200ms (network dependent)
- FixPermissions: ~200-1000ms (depends on file count)
- SearchReplace: ~1-60s (depends on DB size)

### Scaling Considerations
- Suitable for small to medium WordPress installations
- Database size up to 500MB recommended for comfortable operation
- For larger installations, consider:
  - Background job processing
  - Incremental backups
  - Partitioned search-replace

---

## Extension Points

### Adding a New Migration Step

```php
// 1. Create step class
class MyStep implements MigrationStepInterface {
    public function execute(array $data): array {
        return ['ok' => true, 'message' => 'Done'];
    }
    
    public function validate(array $data): array {
        return [];  // No errors
    }
}

// 2. Register in registry (in application bootstrap)
$registry->register('my_step', MyStep::class);

// 3. Execute
$result = $registry->execute('my_step', $backupData);
```

### Adding a New Service

```php
// In ServiceContainer
$this->factories['my_service'] = function (): MyService {
    return new MyService($this->get('translator'));
};

// Usage
$service = $container->get('my_service');
```

---

## Testing Strategy

### Unit Testing Targets
```
✓ DatabaseCredentials validation
✓ MigrationStepRegistry step execution
✓ SearchReplaceService database operations
✓ Translator language selection
✓ MigrationSteps individual logic
```

### Integration Testing Targets
```
✓ BackupModel backup workflow
✓ ServiceContainer service creation
✓ Migration workflow (all steps)
✓ SFTP upload process
```

### Mock Points
```
- Database connections (SearchReplaceService)
- File system operations (BackupModel)
- SFTP operations (SftpUploader)
- Shell execution (DatabaseDumper)
```

---

## Maintenance Guide

### Common Tasks

**Adding a new language:**
1. Create language file in `lang/xx.php`
2. Add translations matching existing keys

**Modifying migration steps:**
1. Edit step class in `src/Migration/Steps/`
2. Update execute() and validate() methods
3. Test via AJAX endpoint

**Extending ServiceContainer:**
1. Add factory method in registerFactories()
2. Create service instance logic
3. Use via $container->get()

---

## Conclusion

The BackUP architecture provides a solid foundation for backup and migration operations with:
- Clear separation of concerns
- Modern design patterns
- Centralized dependency management
- Easy extensibility
- Maintainable codebase
