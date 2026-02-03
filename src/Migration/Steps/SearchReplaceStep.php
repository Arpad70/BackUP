<?php
declare(strict_types=1);
namespace BackupApp\Migration\Steps;

use BackupApp\Migration\MigrationStepInterface;
use BackupApp\Model\DatabaseCredentials;
use BackupApp\Service\SearchReplaceService;
use BackupApp\Service\Translator;

/**
 * SearchReplaceStep - Performs search/replace operations in target database
 * 
 * Wraps the SearchReplaceService with MigrationStepInterface for consistency.
 * Handles database connection, search parameters, and dry-run logic.
 * 
 * Step 5 in the migration process.
 */
class SearchReplaceStep implements MigrationStepInterface
{
    private SearchReplaceService $searchReplaceService;

    public function __construct(Translator $translator)
    {
        $this->searchReplaceService = new SearchReplaceService($translator);
    }

    /**
     * Execute search and replace operation
     * 
     * @param array<string,mixed> $backupData Must contain:
     *   - target_db: Target database name
     *   - target_db_host: DB host (default: localhost)
     *   - target_db_user: DB user (default: root)
     *   - target_db_password: DB password (default: empty)
     *   - target_db_port: DB port (default: 3306)
     *   - search_from: Search string
     *   - search_to: Replace string
     *   - dry_run: Boolean flag for test run (default: true)
     * 
     * @return array<string,mixed> Result with 'ok', 'message', and 'data'
     */
    public function execute(array $backupData): array
    {
        // Bezpečně převeďte na správné typy
        $searchFromRaw = $backupData['search_from'] ?? '';
        $searchToRaw = $backupData['search_to'] ?? '';
        $isDryRunRaw = $backupData['dry_run'] ?? true;
        
        // Safe casting to string
        if (is_string($searchFromRaw)) {
            $searchFrom = $searchFromRaw;
        } else if (is_scalar($searchFromRaw)) {
            $searchFrom = (string)$searchFromRaw;
        } else {
            $searchFrom = '';
        }
        
        if (is_string($searchToRaw)) {
            $searchTo = $searchToRaw;
        } else if (is_scalar($searchToRaw)) {
            $searchTo = (string)$searchToRaw;
        } else {
            $searchTo = '';
        }
        
        // Safe casting to bool
        $isDryRun = is_bool($isDryRunRaw) ? $isDryRunRaw : (bool)$isDryRunRaw;

        // Use DatabaseCredentials to validate and normalize DB parameters
        $dbCredentials = DatabaseCredentials::fromTargetArray($backupData);
        
        // Connect to database
        $connected = $this->searchReplaceService->connectDatabase($dbCredentials);

        if (!$connected) {
            return [
                'ok' => false,
                'message' => 'Chyba: Nelze se připojit k cílové databázi',
                'error' => 'Database connection failed'
            ];
        }

        // Set dry-run mode
        $this->searchReplaceService->setDryRun($isDryRun);

        // Execute search and replace
        $result = $this->searchReplaceService->searchAndReplace(
            $searchFrom,
            $searchTo,
            [],
            [],
            [],
            false
        );

        // Format response
        $changesFound = 0;
        $rowsChecked = 0;
        $updatesM = 0;
        
        if (is_array($result)) {
            if (isset($result['changes_found']) && is_int($result['changes_found'])) {
                $changesFound = $result['changes_found'];
            } elseif (is_scalar($result['changes_found'] ?? null)) {
                $changesFound = (int)$result['changes_found'];
            }
            
            if (isset($result['rows_checked']) && is_int($result['rows_checked'])) {
                $rowsChecked = $result['rows_checked'];
            } elseif (is_scalar($result['rows_checked'] ?? null)) {
                $rowsChecked = (int)$result['rows_checked'];
            }
            
            if (isset($result['updates_made']) && is_int($result['updates_made'])) {
                $updatesM = $result['updates_made'];
            } elseif (is_scalar($result['updates_made'] ?? null)) {
                $updatesM = (int)$result['updates_made'];
            }
        }
        
        $output = sprintf(
            "✅ Nalezeno %d změn\n📊 Zpracováno řádků: %d\n🔄 Aktualizací: %d\n⚠️ %s",
            $changesFound,
            $rowsChecked,
            $updatesM,
            $isDryRun ? '(zkušební běh - bez skutečných změn)' : '(SKUTEČNÉ ZMĚNY)'
        );

        return [
            'ok' => true,
            'message' => $output,
            'data' => $result
        ];
    }

    /**
     * Validate search and replace preconditions
     * 
     * @param array<string,mixed> $backupData
     * @return bool Always true if validation passes
     * 
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(array $backupData): bool
    {
        // Validate database credentials
        $dbCredentials = DatabaseCredentials::fromTargetArray($backupData);
        $dbErrors = $dbCredentials->validate();
        
        if (!empty($dbErrors)) {
            throw new \InvalidArgumentException('Chyba: ' . implode(', ', $dbErrors));
        }

        // Check search string
        if (empty($backupData['search_from'])) {
            throw new \InvalidArgumentException('Chyba: Hledaný řetězec nesmí být prázdný');
        }

        // Warn about dry-run if not explicitly set
        if (!isset($backupData['dry_run'])) {
            // Default to true for safety, but don't fail
        }

        return true;
    }

    /**
     * Get step identifier
     */
    public function getName(): string
    {
        return 'search_replace';
    }

    /**
     * Get step description
     */
    public function getDescription(): string
    {
        return 'Hledání a nahrazení adres URL a textů v databázi';
    }
}
