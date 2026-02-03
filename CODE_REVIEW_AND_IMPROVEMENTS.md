# Code Review & Architecture Analysis - BackUP aplikace

## 1. DUPLICITNÍ KÓD V POHLEDECH (Views)

### 🔴 KRITICKÝ PROBLÉM: Environment diagnostics se opakuje 3× 

**Lokace:**
- `src/View/form.php` - řádky 50-129 (80 řádků)
- `src/View/result.php` - řádky 79-158 (80 řádků)
- `src/View/migration.php` - NEBYL PŘIDÁN (měl by být)

**Duplikovaný kód:**
```php
<!-- mysqldump -->
<!-- zip ext -->
<!-- phpseclib -->
<!-- ssh2 ext -->
<!-- tmp writable -->
```

Každý blok je identický, pouze se mění `$env` a styly.

### ✅ ŘEŠENÍ: Vytvořit ViewComponent třídu

```php
// src/View/Components/EnvironmentDiagnosticsComponent.php
namespace BackupApp\View\Components;

class EnvironmentDiagnosticsComponent
{
    public static function render(array $env, \BackupApp\Service\Translator $translator): string
    {
        // HTML kód pro environment diagnostics
    }
}
```

---

## 2. PORUŠENÍ MVC ARCHITEKTURY

### 🔴 PROBLÉM: Logika v View souborech

**Soubor:** `src/View/migration.php` a `src/View/form.php`

```php
// Inicializace objektů přímo v view - ŠPATNĚ!
if (!isset($translator)) {
    $translator = new \BackupApp\Service\Translator('cs', ['fallback' => 'cs']);
}
```

**Problém:** View by měl jen ZOBRAZOVAT data, ne inicializovat služby.

### ✅ ŘEŠENÍ: 
Move to Controller - všechny služby musí být inicializovány v Controlleru a předány do View.

---

## 3. DUPLIKOVANÁ INICIALIZACE OBJEKTŮ

### 🔴 PROBLÉM: Opakovaná inicializace Translatoru

**Lokace:**
- `src/Controller/BackupController.php` řádek 29
- `src/View/migration.php` řádek 10
- `src/View/form.php` řádek X (pokud existuje)
- `src/View/result.php` řádek X (pokud existuje)

**Řešení:** Inicializovat v Controlleru jednou a předat všem pohledům.

---

## 4. PORUŠENÍ SINGLE RESPONSIBILITY PRINCIPLE

### 🔴 PROBLÉM: BackupController je příliš obsáhlý

**Velikost:** 461 řádků - moc funkcí v jedné třídě

**Odpovědnosti:**
- Session management
- Jazyk/lokalizace
- Zpracování POST dat
- Validace formuláře
- Chyba SSH klíčů
- Spouštění migračních kroků
- ... a více

### ✅ ŘEŠENÍ: Rozdělit na menší třídy

```
RequestHandler (session, request data)
FormValidator (validace vstupů)
MigrationStepProcessor (migration steps)
KeyValidator (validace SSH klíčů)
```

---

## 5. OPAKOVANÝ KÓD PRO ZPRACOVÁNÍ DB PARAMETRŮ

### 🔴 PROBLÉM: Duplicitní zpracování DB parametrů

**Lokace:**
- `BackupModel::runBackup()` řádky 60-85
- `SearchReplaceService::connectDatabase()` řádky 22-33
- `BackupController::handleMigrationStep()` - opakuje se pro search_replace

```php
// Pattern se opakuje:
$dbHost = $data['db_host'] ?? null;
if (!is_string($dbHost) || $dbHost === '') {
    $dbHost = '127.0.0.1';
}
// ... opakuje se pro user, pass, name, port
```

### ✅ ŘEŠENÍ: Vytvořit DatabaseCredentials třídu

```php
// src/Model/DatabaseCredentials.php
class DatabaseCredentials
{
    public function __construct(array $data)
    {
        $this->host = $this->validateHost($data);
        $this->user = $this->validateUser($data);
        $this->password = $this->validatePassword($data);
        // ...
    }
}
```

---

## 6. CHYBÍ SEPARACE CONCERNS V CONTROLLERU

### 🔴 PROBLÉM: Controller obsahuje business logiku

```php
// V BackupController::handleMigrationStep()
case 'clear_caches':
    $targetPath = rtrim($backupData['target_path'], '/');
    $cachesPaths = [
        $targetPath . '/wp-content/cache',
        $targetPath . '/wp-content/plugins/*/cache',
        // ... globální cesty
    ];
    
    $cleared = 0;
    foreach ($cachesPaths as $path) {
        // Manipulace se soubory
    }
```

Mělo by být v Service třídě, ne v Controlleru.

### ✅ ŘEŠENÍ: Vytvořit WPCacheManager service

```php
// src/Service/WPCacheManager.php
class WPCacheManager
{
    public function clearCaches(string $targetPath): int
    {
        // Logika pro mazání cache
    }
}
```

---

## 7. NEDOSTÁVÁ SE ABSTRAKCE PRO MIGRACE

### 🔴 PROBLÉM: Migrace jsou rozptýleny v Controlleru

```php
case 'search_replace':
case 'clear_caches':
case 'verify':
case 'fix_permissions':
```

Všechny migrace by měly mít společné rozhraní.

### ✅ ŘEŠENÍ: Migration Strategy pattern

```php
// src/Migration/MigrationStepInterface.php
interface MigrationStepInterface
{
    public function execute(array $backupData): array;
    public function validate(array $backupData): bool;
    public function getName(): string;
}

// src/Migration/Steps/SearchReplaceStep.php
class SearchReplaceStep implements MigrationStepInterface
{
    // Implementace
}

// V Controlleru:
$step = $this->migrationRegistry->get($stepName);
$result = $step->execute($backupData);
```

---

## 8. CHYBÍ DEPENDENCY INJECTION

### 🔴 PROBLÉM: Služby jsou vytvářeny přímo v kódu

```php
// V BackupController
new BackupModel(null, null, $translator);
new \BackupApp\Service\SearchReplaceService($translator);
```

### ✅ ŘEŠENÍ: Vytvořit Service Container

```php
// src/Container.php
class Container
{
    private array $services = [];
    
    public function get(string $name)
    {
        // Resolution logika
    }
}

// V index.php
$container = new Container();
$controller = $container->get('BackupController');
$controller->handle();
```

---

## 9. NEDOSTÁVÁ SE GLOBÁLNÍ ERROR HANDLING

### 🔴 PROBLÉM: Error handling je rozptýlen

- `BackupController::handle()` - try/catch
- `BackupModel::runBackup()` - vrací pole s chybami
- `SearchReplaceService::searchAndReplace()` - vrací pole s chybami
- Views - checks pro undefined variables

### ✅ ŘEŠENÍ: ErrorHandler middleware

```php
// src/Middleware/ErrorHandler.php
class ErrorHandler
{
    public function handle(\Throwable $e): void
    {
        // Centrální error handling
    }
}
```

---

## 10. VALIDACE PARAMETRŮ JE ROZPTÝLENA

### 🔴 PROBLÉM: Validace v různých místech

```php
// BackupModel
if (!is_string($dbHost) || $dbHost === '') { ... }

// SearchReplaceService
if (empty($search)) { ... }

// BackupController
if (empty($backupData['target_db'])) { ... }
```

### ✅ ŘEŠENÍ: Validator třídy

```php
// src/Validator/DatabaseValidator.php
class DatabaseValidator
{
    public function validateCredentials(array $data): array
    {
        // Vrátí validní data nebo vyjimku
    }
}
```

---

## 11. HLEDANÁ VYLEPŠENÍ - SPECIFICKY V KÓDU

### A) BackupModel je gigantická (601 řádků)

**Problémy:**
- `runBackup()` dělá příliš mnoho - mělo by se rozdělit
- Míchá file operations, DB operations, compression
- `setProgress()` je private, ale měl by být mockable pro testy

### B) SearchReplaceService má opakovaný pattern

```php
private function recursiveUnserializeReplace() // 35 řádků
private function replaceInTable() // 120 řádků
private function getAllTables() // 20 řádků
```

Lze zjednodušit pomocí helper metod.

### C) View HTML je hardcoded

- Styly jsou inline v HTML
- Komponenty se opakují
- Složité porovnání logiky v šablonách

---

## PRIORITA REFAKTORINGU

### 🔴 KRITICKÉ (Udělejte hned):
1. **Vytvořit View Components** - Environment diagnostics duplicita (3× stejný kód)
2. **Extrahovat DatabaseCredentials** - Validace DB dat v 3 místech
3. **Vytvořit MigrationStep Interface** - Všechny migration kroky by měly být jednotné

### 🟡 VYSOKÁ PRIORITA:
4. **Rozdělit BackupController** - 461 řádků je příliš
5. **Extrahovat logiku do Services** - clear_caches, verify, fix_permissions by neměly být v Controlleru
6. **Vytvořit Service Container** - DI pro všechny služby

### 🟢 NIŽŠÍ PRIORITA:
7. **Zjednoduš SearchReplaceService** - Opakující se pattern
8. **Centrální error handling** - ErrorHandler middleware
9. **Komplexnější testování** - Vylepšit pokrytí testů

---

## DOPORUČENÉ STRUKTURY PO REFAKTORINGU

```
BackUP/
├── src/
│   ├── Container.php (DI Container)
│   ├── Controller/
│   │   ├── BackupController.php (ZMENŠENO)
│   │   └── RequestHandler.php (NOVÉ)
│   ├── Service/
│   │   ├── SearchReplaceService.php (EXISTUJÍCÍ)
│   │   ├── WPCacheManager.php (NOVÉ)
│   │   ├── WPVerifier.php (NOVÉ)
│   │   ├── PermissionManager.php (NOVÉ)
│   │   └── Validator/
│   │       ├── DatabaseValidator.php (NOVÉ)
│   │       └── ParameterValidator.php (NOVÉ)
│   ├── Model/
│   │   ├── BackupModel.php (ZMENŠENO)
│   │   ├── DatabaseCredentials.php (NOVÉ)
│   │   └── MigrationResult.php (NOVÉ)
│   ├── Migration/
│   │   ├── MigrationStepInterface.php (NOVÉ)
│   │   └── Steps/
│   │       ├── ClearCachesStep.php (NOVÉ)
│   │       ├── VerifyStep.php (NOVÉ)
│   │       ├── FixPermissionsStep.php (NOVÉ)
│   │       └── SearchReplaceStep.php (NOVÉ)
│   ├── View/
│   │   ├── Components/
│   │   │   ├── EnvironmentDiagnosticsComponent.php (NOVÉ)
│   │   │   └── StepComponent.php (NOVÉ)
│   │   └── ... (ostatní view soubory)
│   └── Middleware/
│       └── ErrorHandler.php (NOVÉ)
```

---

## PŘÍKLADY KÓDU PO REFAKTORINGU

### Příklad 1: View Component

**Teď:**
```php
<!-- 80 řádků duplikovaného kódu v 3 souborech -->
```

**Po refaktoringu:**
```php
<?php $component = new EnvironmentDiagnosticsComponent(); ?>
<?= $component->render($env, $translator) ?>
```

### Příklad 2: DatabaseCredentials

**Teď:**
```php
// Opakuje se v 3 místech
$dbHost = $data['db_host'] ?? null;
if (!is_string($dbHost) || $dbHost === '') {
    $dbHost = '127.0.0.1';
}
```

**Po refaktoringu:**
```php
$credentials = DatabaseCredentials::fromArray($data);
$host = $credentials->getHost(); // '127.0.0.1' (defaultně)
```

### Příklad 3: Migration Steps

**Teď:**
```php
case 'clear_caches':
    // 20 řádků logiky v Controlleru
    break;
```

**Po refaktoringu:**
```php
$step = new ClearCachesStep($backupData);
if ($step->validate()) {
    $result = $step->execute();
}
```

---

## SHRNUTÍ PROBLÉMŮ

| Problém | Lokace | Závaž | Řešení |
|---------|--------|-------|--------|
| **3× duplikace environment view** | form.php, result.php, migration.php | 🔴 | ViewComponent |
| **DB parametry se validují 3×** | BackupModel, SearchReplace, Controller | 🔴 | DatabaseCredentials |
| **Migration logika v Controlleru** | handleMigrationStep() | 🟡 | MigrationStep interface |
| **BackupController 461 řádků** | BackupController.php | 🟡 | Rozdělit na menší třídy |
| **Business logika v Controlleru** | handleMigrationStep() cases | 🟡 | Service classes |
| **Chybí DI Container** | Všude | 🟡 | Service Container |
| **SearchReplace se opakuje** | SearchReplaceService | 🟢 | Refaktor private methods |
| **Nedostává error handling** | Všude | 🟢 | ErrorHandler middleware |
| **Inline styly v HTML** | Všechny view soubory | 🟢 | CSS file |
| **Chybí oddělení concerns** | Obecně | 🟡 | Lepší architektura |

---

## ZÁVĚR

Aplikace je **funkční**, ale porušuje **SOLID principy** a **MVC archekturu**. Klíčové problémy:

1. **Duplikovaný kód** - Zejména HTML templates
2. **Porušena SRP** - Příliš mnoho odpovědností na jednom místě
3. **Nedostatečné oddělení** - View a Controller dělají business logiku
4. **Chybí abstrakce** - Migration kroky nejsou jednotné

**Doporučení:** Provést refactoring podle výše uvedeného plánu. Začít **kritickými problémy** (View Components, DatabaseCredentials) a postupovat ke zbytku.
