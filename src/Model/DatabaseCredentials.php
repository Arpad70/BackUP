<?php
declare(strict_types=1);
namespace BackupApp\Model;

/**
 * DatabaseCredentials - Value Object for Database Connection Parameters
 * 
 * Eliminates duplicated database parameter validation across:
 * - BackupModel::runBackup() (5 parameters × 3 validation patterns)
 * - SearchReplaceService::connectDatabase()
 * - BackupController::handleMigrationStep()
 * 
 * Reduces code duplication by ~50 lines
 */
class DatabaseCredentials
{
    private string $host;
    private int $port;
    private string $user;
    private string $password;
    private string $database;

    /**
     * Create from POST/request array
     * 
     * @param array<string,mixed> $data Source data (typically $_POST or $input)
     * @param string $prefix Key prefix ('db_', 'target_db_', etc.)
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data, string $prefix = 'db_'): self
    {
        $host_raw = $data[$prefix . 'host'] ?? '';
        $port_raw = $data[$prefix . 'port'] ?? 3306;
        $user_raw = $data[$prefix . 'user'] ?? '';
        $pass_raw = $data[$prefix . 'pass'] ?? $data[$prefix . 'password'] ?? '';
        $database_raw = $data[$prefix . 'database'] ?? '';
        
        // Bezpečně převeďte na stringy
        $host = is_scalar($host_raw) ? (string)$host_raw : '';
        $user = is_scalar($user_raw) ? (string)$user_raw : '';
        $pass = is_scalar($pass_raw) ? (string)$pass_raw : '';
        $database = is_scalar($database_raw) ? (string)$database_raw : '';
        
        return new self(
            self::validateString($host, 'localhost'),
            self::validatePort($port_raw, 3306),
            self::validateString($user, 'root'),
            self::validateString($pass, ''),
            self::validateString($database, '')
        );
    }

    /**
     * Create with explicit values
     */
    public function __construct(
        string $host = 'localhost',
        int $port = 3306,
        string $user = 'root',
        string $password = '',
        string $database = ''
    ) {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Invalid port: ' . $port);
        }

        $this->host = $host;
        $this->port = $port;
        $this->user = $user ?: 'root';
        $this->password = $password;
        $this->database = $database;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Get connection string for display
     */
    public function getConnectionString(): string
    {
        return sprintf(
            '%s@%s:%d/%s',
            $this->user,
            $this->host,
            $this->port,
            $this->database ?: '(no database)'
        );
    }

    /**
     * Validate and normalize string value
     */
    private static function validateString(string $value, string $default = ''): string
    {
        if ($value === '') {
            return $default;
        }
        return trim($value);
    }

    /**
     * Validate and normalize port value
     * 
     * @param mixed $value
     * @param int $default
     * @return int
     */
    private static function validatePort(mixed $value, int $default = 3306): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Create for target database (prefixed with 'target_')
     * 
     * @param array<string,mixed> $data
     * @return self
     */
    public static function fromTargetArray(array $data): self
    {
        return self::fromArray($data, 'target_db_');
    }

    /**
     * Create from wp-config.php constants
     * 
     * @param array<string,mixed> $wpConfig
     * @return self
     */
    public static function fromWordPressConfig(array $wpConfig): self
    {
        $host = is_scalar($wpConfig['db_host'] ?? null) ? (string)($wpConfig['db_host'] ?? 'localhost') : 'localhost';
        $user = is_scalar($wpConfig['db_user'] ?? null) ? (string)($wpConfig['db_user'] ?? 'root') : 'root';
        $password = is_scalar($wpConfig['db_password'] ?? null) ? (string)($wpConfig['db_password'] ?? '') : '';
        $database = is_scalar($wpConfig['db_name'] ?? null) ? (string)($wpConfig['db_name'] ?? '') : '';
        
        return new self(
            $host,
            3306,
            $user,
            $password,
            $database
        );
    }

    /**
     * Convert to array for backward compatibility
     * 
     * @return array<string,string|int>
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'password' => $this->password,
            'database' => $this->database,
        ];
    }

    /**
     * Validate all required fields
     * 
     * @return array<int,string>
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->host)) {
            $errors[] = 'Database host is required';
        }

        if (!$this->user) {
            $errors[] = 'Database user is required';
        }

        if (!$this->database) {
            $errors[] = 'Database name is required';
        }

        if ($this->port < 1 || $this->port > 65535) {
            $errors[] = 'Database port must be between 1 and 65535';
        }

        return $errors;
    }

    /**
     * Check if credentials are valid
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }
}
