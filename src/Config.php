<?php
namespace BackupApp;

class Config
{
    /**
     * Load database credentials from wp-config.php
     */
    public static function loadWordPressConfig()
    {
        $wpConfig = dirname(__DIR__) . '/../wp-config.php';
        
        if (!file_exists($wpConfig)) {
            return [
                'db_name' => '',
                'db_user' => '',
                'db_password' => '',
                'db_host' => 'localhost',
            ];
        }

        // Read wp-config.php and extract DB constants
        $content = file_get_contents($wpConfig);
        
        return [
            'db_name' => self::extractConstant($content, 'DB_NAME'),
            'db_user' => self::extractConstant($content, 'DB_USER'),
            'db_password' => self::extractConstant($content, 'DB_PASSWORD'),
            'db_host' => self::extractConstant($content, 'DB_HOST'),
        ];
    }

    /**
     * Extract constant value from PHP code using regex
     */
    private static function extractConstant($content, $constant)
    {
        $pattern = "/define\s*\(\s*['\"]" . preg_quote($constant) . "['\"],\s*['\"]?([^'\"]*)['\"]?\s*\)/";
        
        if (preg_match($pattern, $content, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
