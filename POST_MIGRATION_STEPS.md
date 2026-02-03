# Post-Migration Steps (Po-migrační kroky)

## Přehled

Na migrační stránku (`src/View/migration.php`) byly přidány nové post-migrační kroky, které automatizují běžné úkoly potřebné po migraci WordPress webu. Celá logika byla inspirována SRDB aplikací a přizpůsobena pro BackUP systém.

## Nové migrační kroky

### 1-4. Základní kroky (existující)
- **Krok 1**: Vymazání cílového adresáře
- **Krok 2**: Rozbalení souborů
- **Krok 3**: Reset databáze
- **Krok 4**: Import databáze

### 5. Vyhledání a náhrada dat (Search and Replace)
**Soubor**: `src/Service/SearchReplaceService.php`

Zcela nová služba, která implementuje pokročilé vyhledávání a nahrazování v databázi. Hlavní features:

- **Rekurzivní deserialization**: Zpracovává serializované PHP objekty a pole (jako je WordPress meta data)
- **Dry-run mód**: Umožňuje testování bez skutečných změn
- **Batch processing**: Zpracovává velké tabulky po 50 000 řádcích pro úsporu paměti
- **UTF-8 podpora**: Správně pracuje s Unicode znaky
- **Detekce serializovaných dat**: Automaticky rozpozná a zpracuje serializované řetězce
- **Regex podpora**: Volitelně lze použít regulární výrazy

**Použití v controlleru**:
```php
$searchReplaceService = new \BackupApp\Service\SearchReplaceService($translator);
$searchReplaceService->connectDatabase($host, $user, $pass, $database, $port);
$searchReplaceService->setDryRun($isDryRun); // true pro zkušební běh
$result = $searchReplaceService->searchAndReplace($searchFrom, $searchTo);
```

**Příklad**: Nahradit `stara-domena.cz` za `nova-domena.cz` - logika automaticky:
- Nahradí v všech sloupců všech tabulek
- Deserializuje serializované hodnoty (meta hodnoty WordPress)
- Opraví délky serializovaných řetězců
- Vrátí detailní zprávu o počtu změn

### 6. Vyčištění cache
Odstraňuje mezipaměť z:
- Általános WordPress cache adresář
- W3 Total Cache
- WP Super Cache
- Autoptimize
- Pluginy se svými cache složkami

### 7. Ověření instalace
Kontroluje přítomnost kritických souborů a adresářů:
- Soubory: `wp-load.php`, `wp-config.php`, `index.php`
- Adresáře: `wp-content`, `wp-admin`, `wp-includes`

Zajišťuje, že migrace byla úspěšná a WordPress je plně funkční.

### 8. Nastavení oprávnění
Automaticky nastaví správná oprávnění:
- Adresáře: `755`
- Soubory: `644`
- Speciální adresáře: `755` (wp-content, uploads, themes, plugins)

## Implementační detaily

### SearchReplaceService (`src/Service/SearchReplaceService.php`)

Klíčové metody:

1. **connectDatabase()** - Připojení k MySQL databázi
2. **setDryRun()** - Nastavit mód zkušebního běhu
3. **searchAndReplace()** - Hlavní metoda pro vyhledání a náhradu
4. **recursiveUnserializeReplace()** - Rekurzivní zpracování serializovaných dat
5. **isSerialized()** - Detekce serializovaných řetězců
6. **closeConnection()** - Správné zavření spojení

### BackupController (`src/Controller/BackupController.php`)

Přidané case statements v `handleMigrationStep()`:

```php
case 'search_replace':
    // Vyhledání a náhrada
    
case 'clear_caches':
    // Vyčištění cache
    
case 'verify':
    // Ověření instalace
    
case 'fix_permissions':
    // Nastavení oprávnění
```

### Migration View (`src/View/migration.php`)

Nové UI komponenty:
- Input pole pro vyhledavný a nahrazovaný řetězec
- Checkbox pro dry-run mód
- Visuální feedback (emojis, barevné stavy)
- JavaScript logika pro asynchronní spouštění kroků

## Překlady

Přidány nové klíče pro všechny tři jazyky (cs, en, sk):

### Česky (lang/cs.php)
- `migration_step_search_replace`
- `migration_search_replace_desc`
- `migration_search_from`
- `migration_search_to`
- `migration_dry_run`
- `migration_execute_search_replace`
- `migration_step_clear_caches`
- `migration_clear_caches_desc`
- `migration_clear_caches_button`
- `migration_step_verify`
- `migration_verify_desc`
- `migration_verify_button`
- `migration_step_fix_permissions`
- `migration_fix_permissions_desc`
- `migration_fix_permissions_button`
- `error_db_not_connected`
- `error_search_empty`
- `migration_search_complete`
- `migration_cache_cleared`
- `migration_verification_passed`
- `migration_permissions_fixed`

Stejné klíče s příslušnými překlady jsou v `lang/en.php` a `lang/sk.php`.

## Bezpečnost

1. **SQL Injection ochrana**: Všechny parametry jsou escapovány přes `mysqli::real_escape_string()`
2. **Práva souboru**: Striktně nastavena oprávnění
3. **Dry-run mód**: Umožňuje testování bez rizika
4. **Validace**: Kontrol existence kritických souborů a připojení

## Workflow migrace

```
1. Vymazat cílový adresář
   ↓
2. Rozbalit soubory
   ↓
3. Reset cílové databáze
   ↓
4. Import databáze
   ↓
5. ✨ Vyhledání a náhrada (Nové!)
   ├─ Najít starou doménu/cestu
   ├─ Nahradit novou
   └─ Opravit serializovaná data
   ↓
6. ✨ Vyčistit cache (Nové!)
   ├─ W3 Total Cache
   ├─ WP Super Cache
   └─ Ostatní pluginy
   ↓
7. ✨ Ověřit instalaci (Nové!)
   ├─ Zkontrolovat kritické soubory
   └─ Zkontrolovat kritické adresáře
   ↓
8. ✨ Nastavit oprávnění (Nové!)
   ├─ Soubory: 644
   ├─ Adresáře: 755
   └─ Special dirs: 755
   ↓
✅ Migrace dokončena!
```

## Příklady použití

### Dry-run vyhledání a náhrady
1. Vložit starou doménu: `old-domain.com`
2. Vložit novou doménu: `new-domain.com`
3. Zaškrtnout "Zkušební běh"
4. Kliknout na "Spustit vyhledání a náhradu"
5. Zobrazit se počet nalezených změn bez změny databáze

### Live vyhledání a náhrady
1. Vložit starou cestu: `/var/www/old-path`
2. Vložit novou cestu: `/var/www/new-path`
3. **Odškrtnout** "Zkušební běh"
4. Kliknout na "Spustit vyhledání a náhradu"
5. Databáze bude skutečně aktualizována

## Poznámky

- Serializovaná data se správně deserializují a znovu serializují
- Délky řetězců v serializovaných datech jsou automaticky opraveny
- Velké tabulky jsou zpracovány po částech pro úsporu paměti
- Všechny operace jsou logovány v aplikačním logu
- Dry-run mód je bezpečný pro testování

## Budoucí vylepšení

- [ ] Možnost vybrat konkrétní tabulky
- [ ] Regex podpora v UI
- [ ] Výběr konkrétních sloupců
- [ ] Statistiky jednotlivých tabulek
- [ ] Export statistik do CSV
