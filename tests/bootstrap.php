<?php
/**
 * BackUP Test Suite Bootstrap
 * 
 * This file is executed before running the test suite.
 * It sets up the environment and autoloads necessary files.
 */

// Get the root directory
$rootDir = dirname(__DIR__);

// Load composer autoloader
if (file_exists($rootDir . '/vendor/autoload.php')) {
    require_once $rootDir . '/vendor/autoload.php';
}

// Define test constants
define('BACKUP_ROOT_DIR', $rootDir);
define('BACKUP_TEST_DIR', __DIR__);
define('BACKUP_LANG_DIR', $rootDir . '/lang');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup test environment
class TestEnvironment
{
    /**
     * Initialize test environment
     */
    public static function setup(): void
    {
        // Set timezone
        date_default_timezone_set('UTC');
        
        // Create temporary directories if needed
        $logDir = BACKUP_ROOT_DIR . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }
    
    /**
     * Clean up test environment
     */
    public static function teardown(): void
    {
        // Clean up any test files
    }
}

// Initialize test environment
TestEnvironment::setup();

// Register shutdown function for cleanup
register_shutdown_function([TestEnvironment::class, 'teardown']);
