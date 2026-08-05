<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\CreateTicketCommand;
use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Drives the interactive wizard end to end with a mocked Jira client.
 *
 * The wizard is the half of `create` that the non-interactive tests can never
 * reach, and it is where the labels prompt and the attachment loop live.
 */
class CreateTicketCommandWizardTest extends TestCase
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
            'jira_email' => 'tester@example.com',
            'jira_token' => 'test-token',
        ]));
        $_SERVER['HOME'] = $this->tempHome;

        $this->client = $this->createMock(JiraApiClient::class);
        $this->client->method('testConnection')->willReturn(true);
        $this->client->method('getProjects')->willReturn([
            ['key' => 'ALDO', 'name' => 'Aldo Project'],
        ]);
        $this->client->method('getIssueTypes')->willReturn([
            ['id' => '10001', 'name' => 'Task', 'subtask' => false],
        ]);
        $this->client->method('getPriorities')->willReturn([
            ['id' => '3', 'name' => 'Medium'],
        ]);
        $this->client->method('getAssignableUsers')->willReturn([
            ['accountId' => 'acc-1', 'displayName' => 'Ada Lovelace', 'emailAddress' => 'ada@example.com'],
        ]);
        // Returning nothing for these two skips their prompts, keeping the
        // scripted answers below focused on the parts under test.
        $this->client->method('getActiveSprint')->willReturn(null);
        $this->client->method('getEpics')->willReturn([]);

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
     * @param array<int, string> $answers project, type, summary, description,
     *                                    priority, assignee, labels, attachment, confirm
     */
    private function runWizard(array $answers): CommandTester
    {
        $tester = new CommandTester($this->command);
        $tester->setInputs($answers);
        $tester->execute([]);

        return $tester;
    }

    /**
     * @return array<int, string>
     */
    private function answers(string $labels = '', string $confirm = 'y'): array
    {
        return ['0', '0', 'A summary', 'A description', 'skip', 'unassigned', $labels, '', $confirm];
    }

    public function testWizardCreatesATicketAndSendsTheLabelsItCollected(): void
    {
        $captured = null;
        $this->client->expects($this->once())
            ->method('createIssue')
            ->willReturnCallback(function (array $payload) use (&$captured): array {
                $captured = $payload;

                return ['key' => 'ALDO-42'];
            });

        $tester = $this->runWizard($this->answers(' backend , upgrade ,backend'));

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertNotNull($captured);
        $this->assertSame(['backend', 'upgrade'], $captured['fields']['labels']);
        $this->assertSame('ALDO', $captured['fields']['project']['key']);
        $this->assertSame('A summary', $captured['fields']['summary']);
        $this->assertStringContainsString('ALDO-42', $tester->getDisplay());
    }

    public function testWizardShowsTheCollectedLabelsInTheConfirmationSummary(): void
    {
        $this->client->method('createIssue')->willReturn(['key' => 'ALDO-42']);

        $tester = $this->runWizard($this->answers('backend,upgrade'));

        $this->assertStringContainsString('Labels:', $tester->getDisplay());
        $this->assertStringContainsString('backend, upgrade', $tester->getDisplay());
    }

    public function testAnEmptyLabelsAnswerOmitsTheFieldRatherThanSendingAnEmptyList(): void
    {
        $captured = null;
        $this->client->method('createIssue')->willReturnCallback(function (array $payload) use (&$captured): array {
            $captured = $payload;

            return ['key' => 'ALDO-42'];
        });

        $this->runWizard($this->answers(''));

        $this->assertArrayNotHasKey('labels', $captured['fields']);
    }

    public function testDecliningTheConfirmationCreatesNothing(): void
    {
        $this->client->expects($this->never())->method('createIssue');

        $tester = $this->runWizard($this->answers('backend', 'n'));

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('cancelled', $tester->getDisplay());
    }

    public function testWizardDescriptionIsRenderedAsAdf(): void
    {
        $captured = null;
        $this->client->method('createIssue')->willReturnCallback(function (array $payload) use (&$captured): array {
            $captured = $payload;

            return ['key' => 'ALDO-42'];
        });

        $answers = ['0', '0', 'A summary', '# Heading', 'skip', 'unassigned', '', '', 'y'];
        $this->runWizard($answers);

        $this->assertSame('doc', $captured['fields']['description']['type']);
        $this->assertSame('heading', $captured['fields']['description']['content'][0]['type']);
    }

    public function testAssigneeChosenInTheWizardReachesThePayload(): void
    {
        $captured = null;
        $this->client->method('createIssue')->willReturnCallback(function (array $payload) use (&$captured): array {
            $captured = $payload;

            return ['key' => 'ALDO-42'];
        });

        $answers = ['0', '0', 'A summary', 'desc', 'skip', '0', '', '', 'y'];
        $this->runWizard($answers);

        $this->assertSame(['accountId' => 'acc-1'], $captured['fields']['assignee']);
    }
}
