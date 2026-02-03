<?php
namespace BackupApp\Tests\Unit\Model;

use BackupApp\Model\DatabaseCredentials;
use PHPUnit\Framework\TestCase;

class DatabaseCredentialsTest extends TestCase
{
    /**
     * Test creating DatabaseCredentials from array with db_ prefix
     */
    public function testFromArrayCreatesValidInstance(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'wordpress',
            'db_password' => 'secretpass123',
            'db_database' => 'wp_database',
            'db_port' => '3306',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertInstanceOf(DatabaseCredentials::class, $creds);
        $this->assertEquals('localhost', $creds->getHost());
        $this->assertEquals('wordpress', $creds->getUser());
        $this->assertEquals('secretpass123', $creds->getPassword());
        $this->assertEquals('wp_database', $creds->getDatabase());
        $this->assertEquals(3306, $creds->getPort());
    }
    
    /**
     * Test creating DatabaseCredentials from target_ prefixed array
     */
    public function testFromTargetArrayCreatesValidInstance(): void
    {
        $data = [
            'target_db_host' => 'remote.example.com',
            'target_db_user' => 'remote_user',
            'target_db_password' => 'remote_pass456',
            'target_db_database' => 'target_database',
            'target_db_port' => '3307',
        ];
        
        $creds = DatabaseCredentials::fromTargetArray($data);
        
        $this->assertInstanceOf(DatabaseCredentials::class, $creds);
        $this->assertEquals('remote.example.com', $creds->getHost());
        $this->assertEquals('remote_user', $creds->getUser());
        $this->assertEquals('remote_pass456', $creds->getPassword());
        $this->assertEquals('target_database', $creds->getDatabase());
        $this->assertEquals(3307, $creds->getPort());
    }
    
    /**
     * Test validation returns errors for missing required fields
     */
    public function testValidateReturnsErrorsForMissingFields(): void
    {
        $data = [
            'db_host' => '',  // Missing
            'db_user' => 'user',
            'db_password' => '',  // Missing
            'db_database' => '',  // Missing
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $errors = $creds->validate();
        
        $this->assertIsArray($errors);
        $this->assertGreaterThan(0, count($errors));
    }
    
    /**
     * Test isValid returns true for valid credentials
     */
    public function testIsValidReturnsTrueForValidCredentials(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'root',
            'db_password' => 'password',
            'db_database' => 'mydb',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $this->assertTrue($creds->isValid());
    }
    
    /**
     * Test isValid returns false for invalid credentials
     */
    public function testIsValidReturnsFalseForInvalidCredentials(): void
    {
        $data = [
            'db_host' => '',
            'db_user' => '',
            'db_password' => 'password',
            'db_database' => '',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $this->assertFalse($creds->isValid());
    }
    
    /**
     * Test getters return correct values
     */
    public function testGettersReturnCorrectValues(): void
    {
        $data = [
            'db_host' => 'db.example.com',
            'db_user' => 'appuser',
            'db_password' => 'secure123!@#',
            'db_database' => 'app_db',
            'db_port' => '3308',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertEquals('db.example.com', $creds->getHost());
        $this->assertEquals('appuser', $creds->getUser());
        $this->assertEquals('secure123!@#', $creds->getPassword());
        $this->assertEquals('app_db', $creds->getDatabase());
        $this->assertEquals(3308, $creds->getPort());
    }
    
    /**
     * Test default port is 3306
     */
    public function testDefaultPortIs3306(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
            // Port not provided
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        $this->assertEquals(3306, $creds->getPort());
    }
    
    /**
     * Test constructor with custom prefix
     */
    public function testConstructorWithCustomPrefix(): void
    {
        $data = [
            'custom_host' => 'custom.host.com',
            'custom_user' => 'customuser',
            'custom_password' => 'custompass',
            'custom_database' => 'customdb',
            'custom_port' => '3309',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'custom_');
        
        $this->assertEquals('custom.host.com', $creds->getHost());
        $this->assertEquals('customuser', $creds->getUser());
        $this->assertEquals('customdb', $creds->getDatabase());
        $this->assertEquals(3309, $creds->getPort());
    }
    
    /**
     * Test password with special characters
     */
    public function testPasswordWithSpecialCharacters(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => 'p@$$w0rd!#%&*()[]{}',
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertEquals('p@$$w0rd!#%&*()[]{}', $creds->getPassword());
        $this->assertTrue($creds->isValid());
    }
    
    /**
     * Test with IPv4 address as host
     */
    public function testWithIPv4Address(): void
    {
        $data = [
            'db_host' => '192.168.1.100',
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertEquals('192.168.1.100', $creds->getHost());
    }
    
    /**
     * Test with localhost as host
     */
    public function testWithLocalhostAsHost(): void
    {
        $data = [
            'db_host' => 'localhost',
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
        ];
        
        $creds = DatabaseCredentials::fromArray($data, 'db_');
        
        $this->assertEquals('localhost', $creds->getHost());
        $this->assertTrue($creds->isValid());
    }
    
    /**
     * Test validation message for missing host
     */
    public function testValidationMessageForMissingHost(): void
    {
        $data = [
            // 'db_host' => '',  // Missing host - will use 'localhost' default
            'db_user' => 'user',
            'db_password' => 'pass',
            'db_database' => 'db',
        ];
        
        // Manually create with empty host to trigger validation error
        $creds = new DatabaseCredentials('', 3306, 'user', 'pass', 'db');
        $errors = $creds->validate();
        
        $this->assertGreaterThan(0, count($errors));
        // At least one error should mention host
        $hostErrors = array_filter($errors, function($error) {
            return stripos($error, 'host') !== false;
        });
        $this->assertGreaterThan(0, count($hostErrors));
    }
}
