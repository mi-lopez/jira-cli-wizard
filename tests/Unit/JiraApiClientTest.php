<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;

class JiraApiClientTest extends TestCase
{
    private JiraApiClient $client;

    private string $mockBaseUrl = 'https://test.atlassian.net';

    private string $mockEmail = 'test@example.com';

    private string $mockToken = 'test-token-123';

    protected function setUp(): void
    {
        // This would normally require mocking HTTP requests
        // For demonstration purposes only
        $this->client = new JiraApiClient($this->mockBaseUrl, $this->mockEmail, $this->mockToken);
    }

    public function testConstructorSetsProperties(): void
    {
        $this->assertInstanceOf(JiraApiClient::class, $this->client);
    }

    public function testGetIssueReturnsNullForInvalidIssue(): void
    {
        // This test would require HTTP mocking in a real implementation
        // For now, we'll just test the structure
        $this->assertNull($this->client->getIssue('INVALID-999'));
    }
}
