<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\ConfigManager;
use PHPUnit\Framework\TestCase;

class ConfigManagerTest extends TestCase
{
    private ConfigManager $configManager;

    private string $testConfigFile;

    protected function setUp(): void
    {
        // Create a temporary config file for testing
        $this->testConfigFile = sys_get_temp_dir() . '/test-jira-config-' . uniqid() . '.json';

        // Create ConfigManager with test file
        $this->configManager = new ConfigManager($this->testConfigFile);
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
    }

    public function testIsConfiguredReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse($this->configManager->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWhenAllFieldsSet(): void
    {
        $this->configManager->set('jira_url', 'https://test.atlassian.net');
        $this->configManager->set('jira_email', 'test@example.com');
        $this->configManager->set('jira_token', 'test-token-123');

        $this->assertTrue($this->configManager->isConfigured());
    }

    public function testSetAndGet(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->configManager->set($key, $value);
        $this->assertEquals($value, $this->configManager->get($key));
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $this->assertNull($this->configManager->get('non_existent_key'));
    }

    public function testSaveAndLoad(): void
    {
        $this->configManager->set('jira_url', 'https://test.atlassian.net');
        $this->configManager->set('jira_email', 'test@example.com');

        $this->assertTrue($this->configManager->save());

        // Create new instance to test loading
        $newConfigManager = new ConfigManager($this->testConfigFile);

        $this->assertEquals('https://test.atlassian.net', $newConfigManager->get('jira_url'));
        $this->assertEquals('test@example.com', $newConfigManager->get('jira_email'));
    }

    public function testValidateJiraUrl(): void
    {
        // Valid URLs
        $this->assertTrue($this->configManager->validateJiraUrl('https://company.atlassian.net'));
        $this->assertTrue($this->configManager->validateJiraUrl('https://company.atlassian.net/'));
        $this->assertTrue($this->configManager->validateJiraUrl('https://jira.company.com'));

        // Invalid URLs
        $this->assertFalse($this->configManager->validateJiraUrl('http://company.atlassian.net')); // HTTP
        $this->assertFalse($this->configManager->validateJiraUrl('not-a-url'));
        $this->assertFalse($this->configManager->validateJiraUrl(''));
        $this->assertFalse($this->configManager->validateJiraUrl('ftp://company.com'));
    }

    public function testValidateEmail(): void
    {
        // Valid emails
        $this->assertTrue($this->configManager->validateEmail('test@example.com'));
        $this->assertTrue($this->configManager->validateEmail('user.name+tag@domain.co.uk'));

        // Invalid emails
        $this->assertFalse($this->configManager->validateEmail('invalid-email'));
        $this->assertFalse($this->configManager->validateEmail(''));
        $this->assertFalse($this->configManager->validateEmail('@domain.com'));
        $this->assertFalse($this->configManager->validateEmail('user@'));
    }

    public function testValidateToken(): void
    {
        // Valid tokens
        $this->assertTrue($this->configManager->validateToken('valid-token-123'));
        $this->assertTrue($this->configManager->validateToken('abcdefghijklmnop')); // 16 chars

        // Invalid tokens
        $this->assertFalse($this->configManager->validateToken(''));
        $this->assertFalse($this->configManager->validateToken('short')); // Too short
        $this->assertFalse($this->configManager->validateToken('   ')); // Whitespace
    }

    public function testClear(): void
    {
        $this->configManager->set('jira_url', 'https://test.atlassian.net');
        $this->configManager->save();

        $this->assertTrue($this->configManager->clear());
        $this->assertFalse($this->configManager->isConfigured());
        $this->assertFalse(file_exists($this->testConfigFile));
    }

    public function testGetAll(): void
    {
        $this->configManager->set('key1', 'value1');
        $this->configManager->set('key2', 'value2');

        $all = $this->configManager->getAll();

        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }
}
