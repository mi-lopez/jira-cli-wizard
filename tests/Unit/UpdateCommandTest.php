<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\UpdateCommand;
use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class UpdateCommandTest extends TestCase
{
    private UpdateCommand $command;

    /** @var JiraApiClient&\PHPUnit\Framework\MockObject\MockObject */
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(JiraApiClient::class);
        $this->command = new UpdateCommand($this->client);

        $app = new Application();
        $app->add($this->command);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function payloadFor(array $options): array
    {
        $input = new ArrayInput(
            array_merge(['issue-key' => 'ALDO-7'], $options),
            $this->command->getDefinition()
        );

        return $this->command->buildNonInteractivePayload($input, 'ALDO');
    }

    public function testOmittedOptionsProduceAnEmptyFieldSet(): void
    {
        // Every field is optional, and an absent option must not appear in the
        // payload at all -- sending it as null would clear the field in Jira.
        $this->assertSame(['fields' => []], $this->payloadFor([]));
    }

    public function testSummaryAndTypeArePassedThrough(): void
    {
        $payload = $this->payloadFor(['--summary' => 'New title', '--type' => 'Bug']);

        $this->assertSame('New title', $payload['fields']['summary']);
        $this->assertSame('Bug', $payload['fields']['issuetype']['name']);
    }

    public function testDescriptionGoesThroughTheMarkdownConverter(): void
    {
        // update must render markdown the same way create does.
        $payload = $this->payloadFor(['--description' => "# Title\n\n- one\n- two"]);

        $this->assertSame('doc', $payload['fields']['description']['type']);
        $this->assertSame('heading', $payload['fields']['description']['content'][0]['type']);
        $this->assertSame('bulletList', $payload['fields']['description']['content'][1]['type']);
    }

    public function testEpicKeyIsUppercased(): void
    {
        $this->assertSame('ALDO-10', $this->payloadFor(['--epic' => 'aldo-10'])['fields']['parent']['key']);
    }

    public function testParentIsAnAliasForEpic(): void
    {
        $this->assertSame('ALDO-11', $this->payloadFor(['--parent' => 'ALDO-11'])['fields']['parent']['key']);
    }

    public function testEpicNoneClearsTheParent(): void
    {
        $payload = $this->payloadFor(['--epic' => 'none']);

        $this->assertArrayHasKey('parent', $payload['fields']);
        $this->assertNull($payload['fields']['parent']);
    }

    public function testUnassignedClearsTheAssigneeWithoutCallingJira(): void
    {
        $this->client->expects($this->never())->method('getAssignableUsers');

        $payload = $this->payloadFor(['--assignee' => 'unassigned']);

        $this->assertArrayHasKey('assignee', $payload['fields']);
        $this->assertNull($payload['fields']['assignee']);
    }

    public function testAssigneeIsResolvedToAnAccountId(): void
    {
        $this->client->method('getAssignableUsers')->willReturn([
            ['accountId' => 'acc-9', 'displayName' => 'Grace Hopper', 'emailAddress' => 'grace@example.com'],
        ]);

        $payload = $this->payloadFor(['--assignee' => 'grace@example.com']);

        $this->assertSame(['accountId' => 'acc-9'], $payload['fields']['assignee']);
    }

    public function testUnresolvableAssigneeThrows(): void
    {
        $this->client->method('getAssignableUsers')->willReturn([
            ['accountId' => 'acc-9', 'displayName' => 'Grace Hopper'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->payloadFor(['--assignee' => 'Nobody At All']);
    }

    public function testLabelsAreParsedAndDeduplicated(): void
    {
        $payload = $this->payloadFor(['--labels' => ' backend , upgrade ,backend']);

        $this->assertSame(['backend', 'upgrade'], $payload['fields']['labels']);
    }

    public function testEmptyLabelsClearsTheFieldRatherThanOmittingIt(): void
    {
        // Unlike create, update needs a way to remove every label, so an empty
        // --labels must still emit the key with an empty list.
        $payload = $this->payloadFor(['--labels' => '']);

        $this->assertArrayHasKey('labels', $payload['fields']);
        $this->assertSame([], $payload['fields']['labels']);
    }

    public function testNumericSprintIsCarriedOutsideTheFieldSet(): void
    {
        // Sprint is not a normal field; it goes to the agile endpoint after the
        // edit, so it must not end up inside `fields`.
        $payload = $this->payloadFor(['--sprint' => '42']);

        $this->assertSame(42, $payload['sprint_id']);
        $this->assertArrayNotHasKey('sprint', $payload['fields']);
    }

    public function testActiveSprintIsLookedUp(): void
    {
        $this->client->method('getActiveSprint')->with('ALDO')->willReturn(['id' => 77, 'name' => 'Sprint 7']);

        $this->assertSame(77, $this->payloadFor(['--sprint' => 'active'])['sprint_id']);
    }

    public function testActiveSprintWithNoOpenSprintIsSkipped(): void
    {
        $this->client->method('getActiveSprint')->willReturn(null);

        $this->assertArrayNotHasKey('sprint_id', $this->payloadFor(['--sprint' => 'active']));
    }

    public function testNonNumericSprintIsIgnored(): void
    {
        $this->assertArrayNotHasKey('sprint_id', $this->payloadFor(['--sprint' => 'not-a-sprint']));
    }
}
