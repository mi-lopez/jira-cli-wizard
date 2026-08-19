<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\ViewCommand;
use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;

class ViewCommandTest extends TestCase
{
    private ViewCommand $command;

    /** @var JiraApiClient&\PHPUnit\Framework\MockObject\MockObject */
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(JiraApiClient::class);
        $this->command = new ViewCommand($this->client);

        $app = new Application();
        $app->add($this->command);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function render(array $fields, string $baseUrl = 'https://example.atlassian.net'): string
    {
        $output = new BufferedOutput();
        $this->command->renderIssue($output, ['key' => 'ALDO-7', 'fields' => $fields], $baseUrl);

        return $output->fetch();
    }

    public function testCommandIsConfiguredProperly(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('view', $this->command->getName());
        $this->assertTrue($definition->hasArgument('issue-key'));
        $this->assertTrue($definition->getArgument('issue-key')->isRequired());
        $this->assertTrue($definition->hasOption('json'));
    }

    public function testKeysAreUppercasedSoLowercaseInputWorks(): void
    {
        $this->assertSame('ALDO-1234', ViewCommand::normalizeKey('aldo-1234'));
        $this->assertSame('ALDO-1234', ViewCommand::normalizeKey('  ALDO-1234 '));
    }

    public function testMalformedKeysAreRejectedBeforeCallingJira(): void
    {
        $this->assertNull(ViewCommand::normalizeKey('1234'));
        $this->assertNull(ViewCommand::normalizeKey('ALDO'));
        $this->assertNull(ViewCommand::normalizeKey('ALDO-'));
        $this->assertNull(ViewCommand::normalizeKey('not a key'));
    }

    public function testHeaderShowsKeyAndSummary(): void
    {
        $output = $this->render(['summary' => 'Checkout crashes on retry']);

        $this->assertStringContainsString('ALDO-7', $output);
        $this->assertStringContainsString('Checkout crashes on retry', $output);
    }

    public function testMetadataFieldsAreListed(): void
    {
        $output = $this->render([
            'summary' => 'Something',
            'status' => ['name' => 'In Progress'],
            'issuetype' => ['name' => 'Bug'],
            'priority' => ['name' => 'High'],
            'assignee' => ['displayName' => 'Ada Lovelace'],
            'reporter' => ['displayName' => 'Alan Turing'],
            'project' => ['key' => 'ALDO'],
            'labels' => ['backend', 'urgent'],
            'created' => '2026-08-01T10:22:33.000+0200',
            'updated' => '2026-08-18T16:40:00.000+0200',
        ]);

        $this->assertStringContainsString('In Progress', $output);
        $this->assertStringContainsString('Bug', $output);
        $this->assertStringContainsString('High', $output);
        $this->assertStringContainsString('Ada Lovelace', $output);
        $this->assertStringContainsString('Alan Turing', $output);
        $this->assertStringContainsString('backend, urgent', $output);
        // Jira stamps its own offset; the CLI prints the reader's local time,
        // so the expectation follows the environment's timezone too.
        $this->assertStringContainsString(date('Y-m-d H:i', strtotime('2026-08-01T10:22:33.000+0200')), $output);
        $this->assertStringContainsString(date('Y-m-d H:i', strtotime('2026-08-18T16:40:00.000+0200')), $output);
    }

    public function testEmptyFieldsDoNotTakeUpALine(): void
    {
        // An unassigned ticket should not print a bare "Assignee" label.
        $output = $this->render(['summary' => 'Something', 'assignee' => null, 'labels' => []]);

        $this->assertStringNotContainsString('Assignee', $output);
        $this->assertStringNotContainsString('Labels', $output);
    }

    public function testParentIsShownWithItsSummary(): void
    {
        $output = $this->render([
            'summary' => 'Child',
            'parent' => ['key' => 'ALDO-1', 'fields' => ['summary' => 'The epic']],
        ]);

        $this->assertStringContainsString('ALDO-1 — The epic', $output);
    }

    public function testBrowseUrlIsBuiltFromTheConfiguredInstance(): void
    {
        $output = $this->render(['summary' => 'Something'], 'https://example.atlassian.net/');

        $this->assertStringContainsString('https://example.atlassian.net/browse/ALDO-7', $output);
    }

    public function testUrlIsOmittedWhenNoInstanceIsKnown(): void
    {
        $output = $this->render(['summary' => 'Something'], '');

        $this->assertStringNotContainsString('browse', $output);
    }

    public function testDescriptionIsRenderedAsMarkdown(): void
    {
        $output = $this->render([
            'summary' => 'Something',
            'description' => [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Steps']]],
                    ['type' => 'bulletList', 'content' => [[
                        'type' => 'listItem',
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'open cart']]]],
                    ]]],
                ],
            ],
        ]);

        $this->assertStringContainsString('## Steps', $output);
        $this->assertStringContainsString('- open cart', $output);
    }

    public function testMissingDescriptionSaysSo(): void
    {
        $this->assertStringContainsString('(no description)', $this->render(['summary' => 'Something']));
    }

    public function testJsonOutputIsAFlatSummaryNotJirasRawPayload(): void
    {
        // The API response also carries the changelog and every rendered field;
        // a script asking for --json wants the ticket, not the noise.
        $json = ViewCommand::toArray([
            'key' => 'ALDO-7',
            'fields' => [
                'summary' => 'Something',
                'status' => ['name' => 'Done'],
                'issuetype' => ['name' => 'Bug'],
                'assignee' => null,
                'labels' => ['backend'],
                'parent' => ['key' => 'ALDO-1', 'fields' => ['summary' => 'The epic']],
                'description' => ['type' => 'doc', 'version' => 1, 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'the body']]],
                ]],
            ],
        ], 'https://example.atlassian.net');

        $this->assertSame('ALDO-7', $json['key']);
        $this->assertSame('https://example.atlassian.net/browse/ALDO-7', $json['url']);
        $this->assertSame('Done', $json['status']);
        $this->assertSame('Bug', $json['type']);
        $this->assertSame('ALDO-1', $json['parent']);
        $this->assertSame(['backend'], $json['labels']);
        $this->assertSame('the body', $json['description']);
        $this->assertArrayNotHasKey('changelog', $json);
    }

    public function testJsonOutputNullsMissingFieldsInsteadOfEmptyStrings(): void
    {
        $json = ViewCommand::toArray(['key' => 'ALDO-7', 'fields' => ['summary' => 'Something']], '');

        $this->assertNull($json['assignee']);
        $this->assertNull($json['priority']);
        $this->assertNull($json['url']);
        $this->assertSame([], $json['labels']);
        $this->assertSame('', $json['description']);
    }

    public function testAngleBracketsInContentAreNotTreatedAsConsoleTags(): void
    {
        // Symfony's formatter would swallow (or choke on) <div>-looking text.
        $output = $this->render([
            'summary' => 'Fix <div> rendering',
            'description' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'in <section> tags']]],
            ]],
        ]);

        $this->assertStringContainsString('<div>', $output);
        $this->assertStringContainsString('<section>', $output);
    }
}
