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

    /**
     * @param array<int, array<string, mixed>> $transitions
     */
    private static function match(array $transitions, string $wanted): array
    {
        return UpdateCommand::matchTransitions($transitions, $wanted);
    }

    /** @return array<int, array<string, mixed>> */
    private static function workflow(): array
    {
        return [
            ['id' => '2', 'name' => 'En cours de traitement', 'to' => ['name' => 'En cours de traitement']],
            ['id' => '271', 'name' => 'Annulé', 'to' => ['name' => 'Annulé']],
            ['id' => '281', 'name' => 'En cours', 'to' => ['name' => 'En cours de traitement']],
        ];
    }

    public function testStatusRidesItsOwnKeyAndNeverTheFieldSet(): void
    {
        // Status is not writable through PUT /issue; sending it as a field
        // would make Jira reject the whole update.
        $payload = $this->payloadFor(['--status' => 'En cours']);

        $this->assertSame('En cours', $payload['status']);
        $this->assertSame([], $payload['fields']);
    }

    public function testTransitionsMatchById(): void
    {
        $this->assertSame('271', self::match(self::workflow(), '271')[0]['id']);
    }

    public function testTransitionsMatchByExactNameIgnoringCase(): void
    {
        $this->assertSame('271', self::match(self::workflow(), 'annulé')[0]['id']);
    }

    public function testAnExactNameWinsOverALongerOneItPrefixes(): void
    {
        // 'En cours' is both a transition of its own and the prefix of another;
        // typing it exactly must not be reported as ambiguous.
        $matches = self::match(self::workflow(), 'En cours');

        $this->assertCount(1, $matches);
        $this->assertSame('281', $matches[0]['id']);
    }

    public function testTransitionsMatchByTargetStatusName(): void
    {
        // Users think in terms of the status they want, not the button's label.
        $matches = self::match([
            ['id' => '3', 'name' => '(Re)Assignée', 'to' => ['name' => 'Assignée']],
        ], 'Assignée');

        $this->assertSame('3', $matches[0]['id']);
    }

    public function testAnAmbiguousFragmentReturnsEveryCandidate(): void
    {
        $this->assertCount(2, self::match(self::workflow(), 'cours de'));
    }

    public function testAnUnknownStatusMatchesNothing(): void
    {
        $this->assertSame([], self::match(self::workflow(), 'Done'));
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
