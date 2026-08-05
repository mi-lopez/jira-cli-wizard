<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\CreateTicketCommand;
use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Covers the wiring between `create --assignee` and AssigneeResolver.
 *
 * The resolver itself is tested directly in AssigneeResolverTest; what is
 * exercised here is the part that only runs inside execute() -- resolving "me"
 * against the configured account, and getting the result into the payload.
 */
class CreateTicketCommandAssigneeTest extends TestCase
{
    private string $tempHome;

    private ?string $originalHome;

    /** @var JiraApiClient&\PHPUnit\Framework\MockObject\MockObject */
    private $client;

    private CreateTicketCommand $command;

    protected function setUp(): void
    {
        $this->originalHome = $_SERVER['HOME'] ?? null;
        $this->tempHome = sys_get_temp_dir() . '/jira-cli-wizard-test-' . bin2hex(random_bytes(8));
        mkdir($this->tempHome, 0700, true);
        file_put_contents($this->tempHome . '/.jira-cli-config.json', (string) json_encode([
            'jira_url' => 'https://example.atlassian.net',
            'jira_email' => 'ada@example.com',
            'jira_token' => 'test-token',
        ]));
        $_SERVER['HOME'] = $this->tempHome;

        $this->client = $this->createMock(JiraApiClient::class);
        $this->client->method('getAssignableUsers')->willReturn([
            ['accountId' => 'acc-1', 'displayName' => 'Ada Lovelace', 'emailAddress' => 'ada@example.com'],
            ['accountId' => 'acc-2', 'displayName' => 'Alan Turing', 'emailAddress' => 'alan@example.com'],
        ]);

        $this->command = new CreateTicketCommand($this->client);
        $app = new Application();
        $app->add($this->command);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempHome . '/.jira-cli-config.json');
        @rmdir($this->tempHome);

        if ($this->originalHome === null) {
            unset($_SERVER['HOME']);
        } else {
            $_SERVER['HOME'] = $this->originalHome;
        }
    }

    /**
     * @return array{0: CommandTester, 1: ?array}
     */
    private function dryRunWith(string $assignee): array
    {
        $tester = new CommandTester($this->command);
        $tester->execute([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Assigned ticket',
            '--assignee' => $assignee,
            '--dry-run' => true,
            '--no-interaction' => true,
        ]);

        return [$tester, json_decode($tester->getDisplay(), true)];
    }

    public function testAssigneeIsResolvedIntoThePayload(): void
    {
        [$tester, $payload] = $this->dryRunWith('Alan Turing');

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(['accountId' => 'acc-2'], $payload['fields']['assignee']);
    }

    public function testAssigneeCanBeGivenAsAnEmail(): void
    {
        [, $payload] = $this->dryRunWith('alan@example.com');

        $this->assertSame(['accountId' => 'acc-2'], $payload['fields']['assignee']);
    }

    public function testMeResolvesToTheConfiguredAccount(): void
    {
        // "me" is substituted for jira_email from the config file, which is why
        // this can only be exercised through execute().
        [, $payload] = $this->dryRunWith('me');

        $this->assertSame(['accountId' => 'acc-1'], $payload['fields']['assignee']);
    }

    public function testMeIsCaseInsensitive(): void
    {
        [, $payload] = $this->dryRunWith('ME');

        $this->assertSame(['accountId' => 'acc-1'], $payload['fields']['assignee']);
    }

    public function testAnAmbiguousAssigneeFailsInsteadOfPickingOne(): void
    {
        // "A" matches both people; creating the ticket anyway would assign it to
        // whichever happened to come first.
        [$tester] = $this->dryRunWith('A');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Multiple assignees found', $tester->getDisplay());
    }

    public function testAnUnknownAssigneeFailsWithoutCreatingAnything(): void
    {
        $this->client->expects($this->never())->method('createIssue');

        [$tester] = $this->dryRunWith('Nobody At All');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No assignee found', $tester->getDisplay());
    }
}
