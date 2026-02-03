<?php
declare(strict_types=1);
namespace BackupApp\Service;

use BackupApp\Service\Translator;
use BackupApp\Model\DatabaseCredentials;

class SearchReplaceService
{
    private ?\mysqli $db = null;
    private bool $dry_run = true;
    /**
     * @var array<int,string>
     */
    private array $errors = [];
    private Translator $translator;
    
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }
    
    /**
     * Připojení k databázi
     */
    public function connectDatabase(DatabaseCredentials $dbCredentials): bool
    {
        try {
            $this->db = @new \mysqli(
                $dbCredentials->getHost(),
                $dbCredentials->getUser(),
                $dbCredentials->getPassword(),
                $dbCredentials->getDatabase(),
                $dbCredentials->getPort()
            );
            
            if ($this->db->connect_error) {
                $this->errors[] = 'Chyba připojení: ' . $this->db->connect_error;
                $this->db = null;
                return false;
            }
            
            $this->db->set_charset('utf8mb4');
            return true;
        } catch (\Throwable $e) {
            $this->errors[] = 'Výjimka při připojení: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Nastavit, zda se jedná o dry-run (bez skutečných změn)
     */
    public function setDryRun(bool $dry_run): void
    {
        $this->dry_run = $dry_run;
    }
    
    /**
     * Provést hledání a náhradu v databázi
     * 
     * @param string $search Text k hledání
     * @param string $replace Text nahrazení
     * @param array<int,string> $tables Tabulky na zpracování
     * @param array<int,string> $exclude_tables Vyloučené tabulky
     * @param array<int,string> $exclude_cols Vyloučené sloupce
     * @param bool $regex Použít regulární výrazy
     * @return array<string,mixed>
     */
    public function searchAndReplace(
        string $search,
        string $replace,
        array $tables = [],
        array $exclude_tables = [],
        array $exclude_cols = [],
        bool $regex = false
    ): array {
        if (!$this->db) {
            return [
                'success' => false,
                'error' => $this->translator->translate('error_db_not_connected'),
                'changes' => 0
            ];
        }
        
        if (empty($search)) {
            return [
                'success' => false,
                'error' => $this->translator->translate('error_search_empty'),
                'changes' => 0
            ];
        }
        
        $report = [
            'success' => true,
            'tables_checked' => 0,
            'rows_checked' => 0,
            'changes_found' => 0,
            'updates_made' => 0,
            'table_reports' => [],
            'errors' => [],
            'dry_run' => $this->dry_run
        ];
        
        // Pokud nejsou specifikovány tabulky, použít všechny
        if (empty($tables)) {
            $tables = $this->getAllTables();
        }
        
        foreach ($tables as $table) {
            // Přeskočit vyloučené tabulky
            if (in_array($table, $exclude_tables)) {
                continue;
            }
            
            $table_report = $this->replaceInTable(
                $table,
                $search,
                $replace,
                $exclude_cols,
                $regex
            );
            
            $report['tables_checked']++;
            $report['rows_checked'] += $table_report['rows_checked'];
            $report['changes_found'] += $table_report['changes_found'];
            $report['updates_made'] += $table_report['updates_made'];
            $report['table_reports'][$table] = $table_report;
            
            if (!empty($table_report['errors'])) {
                $errors = $table_report['errors'];
                if (is_array($errors)) {
                    $report['errors'] = array_merge($report['errors'], $errors);
                }
            }
        }
        
        return $report;
    }
    
    /**
     * Získat seznam všech tabulek
     * 
     * @return array<int,string>
     */
    private function getAllTables(): array
    {
        if (!$this->db) {
            return [];
        }
        
        $tables = [];
        $result = $this->db->query('SHOW TABLES');
        
        if ($result instanceof \mysqli_result) {
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0] ?? '';
            }
            $result->free();
        }
        
        return $tables;
    }
    
    /**
     * Nahradit v konkrétní tabulce
     * 
     * @param string $table Název tabulky
     * @param string $search Text k hledání
     * @param string $replace Text nahrazení
     * @param array<int,string> $exclude_cols Vyloučené sloupce
     * @param bool $regex Použít regulární výrazy
     * @return array<string,mixed>
     */
    private function replaceInTable(
        string $table,
        string $search,
        string $replace,
        array $exclude_cols,
        bool $regex
    ): array {
        $report = [
            'table' => $table,
            'rows_checked' => 0,
            'changes_found' => 0,
            'updates_made' => 0,
            'changes' => [],
            'errors' => []
        ];
        
        // Bezpečně escapovat název tabulky
        $table_escaped = '`' . str_replace('`', '``', $table) . '`';
        
        // Získat sloupce
        if (!$this->db) {
            $report['errors'][] = 'Databáze není připojena';
            return $report;
        }
        
        $columns_result = $this->db->query('DESCRIBE ' . $table_escaped);
        if (!$columns_result) {
            $report['errors'][] = 'Nemohu načíst sloupce tabulky: ' . $this->db->error;
            return $report;
        }
        
        $columns = [];
        $primary_key = '';
        
        if ($columns_result instanceof \mysqli_result) {
            while ($row = $columns_result->fetch_assoc()) {
                if (is_array($row)) {
                    $columns[] = $row['Field'] ?? '';
                    if (($row['Key'] ?? '') === 'PRI') {
                        $primary_key = (string)($row['Field'] ?? '');
                    }
                }
            }
            $columns_result->free();
        }
        
        if (!$primary_key) {
            $report['errors'][] = 'Tabulka nemá primární klíč: ' . $table;
            return $report;
        }
        
        // Počítat řádky
        $count_result = $this->db->query('SELECT COUNT(*) as cnt FROM ' . $table_escaped);
        if (!$count_result || !($count_result instanceof \mysqli_result)) {
            $report['errors'][] = 'Chyba při počítání řádků: ' . ($this->db ? $this->db->error : 'Databáze není připojena'); // @phpstan-ignore-line
            return $report;
        }
        $count_row = $count_result->fetch_assoc();
        if (!is_array($count_row)) {
            $report['errors'][] = 'Chyba při načtení počtu řádků';
            return $report;
        }
        $total_rows = (int)($count_row['cnt'] ?? 0);
        $count_result->free();
        
        // Zpracovat po částech (pro velké tabulky)
        $page_size = 50000;
        $pages = ceil($total_rows / $page_size);
        
        for ($page = 0; $page < $pages; $page++) {
            $start = $page * $page_size;
            
            $query = 'SELECT * FROM ' . $table_escaped . ' LIMIT ' . $start . ', ' . $page_size;
            $result = $this->db->query($query);
            
            if (!$result) {
                $report['errors'][] = 'Chyba při čtení: ' . $this->db->error;
                continue;
            }
            
            if ($result instanceof \mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $report['rows_checked']++;
                    
                    $updates = [];
                    $changed = false;
                    
                    foreach ($columns as $column) {
                        // Přeskočit vyloučené sloupce a primární klíč
                        if (in_array($column, $exclude_cols) || $column === $primary_key) {
                            continue;
                        }
                        
                        $old_value = $row[$column] ?? null;
                        $new_value = $this->recursiveUnserializeReplace($search, $replace, $old_value, $regex);
                        
                        if ($new_value !== $old_value) {
                            $report['changes_found']++;
                            $changed = true;
                            $updates[$column] = $new_value;
                            
                            // Zaznamenat změnu (max 50 změn)
                            if (count($report['changes']) < 50) {
                                $report['changes'][] = [
                                    'column' => $column,
                                    'from' => $this->truncateString($old_value, 100),
                                    'to' => $this->truncateString($new_value, 100)
                                ];
                            }
                        }
                    }
                    
                    // Provést update (pokud to není dry-run)
                    if ($changed && !$this->dry_run) {
                        $set_parts = [];
                        foreach ($updates as $col => $val) {
                            $col_escaped = '`' . str_replace('`', '``', $col) . '`';
                            if (!$this->db) { // @phpstan-ignore-line
                                $report['errors'][] = 'Databáze není připojena';
                                break;
                            }
                            $val_str = is_string($val) ? $val : (string)$val;
                            $val_escaped = $this->db->real_escape_string($val_str);
                            $set_parts[] = $col_escaped . ' = \'' . $val_escaped . '\'';
                        }
                        
                        $pk_escaped = '`' . str_replace('`', '``', $primary_key) . '`';
                        $pk_val = $row[$primary_key] ?? '';
                        $pk_val_escaped = $this->db ? $this->db->real_escape_string((string)$pk_val) : ''; // @phpstan-ignore-line
                        
                        $update_query = 'UPDATE ' . $table_escaped . ' SET ' . implode(', ', $set_parts) .
                                        ' WHERE ' . $pk_escaped . ' = \'' . $pk_val_escaped . '\'';
                        
                        if ($this->db && !$this->db->query($update_query)) { // @phpstan-ignore-line
                            $report['errors'][] = 'Chyba při update: ' . $this->db->error;
                        } else {
                            $report['updates_made']++;
                        }
                    }
                }
                $result->free();
            }
        }
        
        return $report;
    }
    
    /**
     * Rekurzivně nahradit v serializovaných datech
     */
    private function recursiveUnserializeReplace(string $search, string $replace, mixed $data, bool $regex): mixed
    {
        if ($data === 'b:0;') {
            return $data;
        }
        
        try {
            if (is_string($data) && $this->isSerialized($data)) {
                $unserialized = @unserialize($data);
                if ($unserialized !== false) {
                    $replaced = $this->recursiveUnserializeReplace($search, $replace, $unserialized, $regex);
                    return serialize($replaced);
                }
            }
            
            if (is_array($data)) {
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->recursiveUnserializeReplace($search, $replace, $value, $regex);
                }
                return $result;
            }
            
            if (is_string($data)) {
                if ($regex) {
                    return @preg_replace($search, $replace, $data) ?? $data;
                } else {
                    return str_replace($search, $replace, $data);
                }
            }
        } catch (\Throwable $e) {
            // Tiše selhat při problému s deserializací
        }
        
        return $data;
    }
    
    /**
     * Zkontrolovat, zda je řetězec serializovaný
     */
    private function isSerialized(mixed $data): bool
    {
        if (!is_string($data)) {
            return false;
        }
        
        $data = trim($data);
        
        if ($data === 'N;') {
            return true;
        }
        
        if (strlen($data) < 4) {
            return false;
        }
        
        if ($data[1] !== ':') {
            return false;
        }
        
        $lastc = substr($data, -1);
        if ($lastc !== ';' && $lastc !== '}') {
            return false;
        }
        
        $token = $data[0];
        switch ($token) {
            case 's':
                return (bool)preg_match('/^s:[0-9]+:".*";$/', $data);
            case 'a':
            case 'O':
            case 'E':
                return (bool)preg_match('/^' . preg_quote($token, '/') . ':[0-9]+:/', $data);
            case 'b':
            case 'i':
            case 'd':
                return (bool)preg_match('/^' . preg_quote($token, '/') . ':[0-9.E+-]+;$/', $data);
        }
        
        return false;
    }
    
    /**
     * Zkrátit řetězec na určitou délku
     * 
     * @param mixed $str
     * @param int $length
     * @return string
     */
    private function truncateString(mixed $str, int $length = 100): string
    {
        if (is_string($str)) {
            if (strlen($str) > $length) {
                return substr($str, 0, $length) . '...';
            }
            return $str;
        }
        
        if ($str === null) {
            return '';
        }
        
        if (is_scalar($str)) {
            $str_val = (string)$str;
            if (strlen($str_val) > $length) {
                return substr($str_val, 0, $length) . '...';
            }
            return $str_val;
        }
        
        return '';
    }
    
    /**
     * Získat chyby
     * 
     * @return array<int,string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Zavřít připojení
     */
    public function closeConnection(): void
    {
        if ($this->db) {
            $this->db->close();
            $this->db = null;
        }
    }
    
    /**
     * Destruktor
     */
    public function __destruct()
    {
        $this->closeConnection();
    }
}
