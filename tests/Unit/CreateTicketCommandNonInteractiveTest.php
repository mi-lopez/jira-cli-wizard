<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\CreateTicketCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;

class CreateTicketCommandNonInteractiveTest extends TestCase
{
    private CreateTicketCommand $command;

    protected function setUp(): void
    {
        $this->command = new CreateTicketCommand();

        $app = new Application();
        $app->add($this->command);
    }

    public function testCommandHasRequiredOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('project'));
        $this->assertTrue($definition->hasOption('type'));
        $this->assertTrue($definition->hasOption('summary'));
        $this->assertTrue($definition->hasOption('description'));
        $this->assertTrue($definition->hasOption('parent'));
        $this->assertTrue($definition->hasOption('epic'));
        $this->assertTrue($definition->hasOption('labels'));
        $this->assertTrue($definition->hasOption('priority'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('assignee'));
    }

    public function testBuildNonInteractivePayloadMinimal(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Test ticket',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Test ticket');

        $this->assertSame('ALDO', $payload['fields']['project']['key']);
        $this->assertSame('Task', $payload['fields']['issuetype']['name']);
        $this->assertSame('Test ticket', $payload['fields']['summary']);
        $this->assertArrayNotHasKey('description', $payload['fields']);
        $this->assertArrayNotHasKey('parent', $payload['fields']);
        $this->assertArrayNotHasKey('priority', $payload['fields']);
        $this->assertArrayNotHasKey('labels', $payload['fields']);
    }

    public function testBuildNonInteractivePayloadWithAssigneeNoClientDoesNotCrash(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Test ticket',
            '--assignee' => 'assignee@example.com',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Test ticket');

        $this->assertSame('ALDO', $payload['fields']['project']['key']);
        $this->assertArrayNotHasKey('assignee', $payload['fields']);
    }

    public function testBuildNonInteractivePayloadProjectKeyIsUppercased(): void
    {
        $input = new ArrayInput([
            '--project' => 'aldo',
            '--type' => 'Task',
            '--summary' => 'Test ticket',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'aldo', 'Task', 'Test ticket');

        $this->assertSame('ALDO', $payload['fields']['project']['key']);
    }

    public function testBuildNonInteractivePayloadWithDescription(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Story',
            '--summary' => 'A story',
            '--description' => 'Some description',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Story', 'A story');

        $this->assertArrayHasKey('description', $payload['fields']);
        $this->assertSame('doc', $payload['fields']['description']['type']);
        $this->assertSame(
            'Some description',
            $payload['fields']['description']['content'][0]['content'][0]['text']
        );
    }

    public function testBuildNonInteractivePayloadWithParent(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Child task',
            '--parent' => 'aldo-10',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Child task');

        $this->assertSame('ALDO-10', $payload['fields']['parent']['key']);
    }

    public function testBuildNonInteractivePayloadEpicAliasForParent(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Child task',
            '--epic' => 'ALDO-5',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Child task');

        $this->assertSame('ALDO-5', $payload['fields']['parent']['key']);
    }

    public function testBuildNonInteractivePayloadWithPriority(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Urgent task',
            '--priority' => 'High',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Urgent task');

        $this->assertSame('High', $payload['fields']['priority']['name']);
    }

    public function testBuildNonInteractivePayloadWithLabels(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Task',
            '--summary' => 'Labelled task',
            '--labels' => 'backend, upgrade, orocommerce',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Task', 'Labelled task');

        $this->assertSame(['backend', 'upgrade', 'orocommerce'], $payload['fields']['labels']);
    }

    public function testBuildNonInteractivePayloadAllOptions(): void
    {
        $input = new ArrayInput([
            '--project' => 'ALDO',
            '--type' => 'Story',
            '--summary' => 'Full story',
            '--description' => 'Full description',
            '--epic' => 'ALDO-1',
            '--priority' => 'Medium',
            '--labels' => 'upgrade,backend',
        ], $this->command->getDefinition());

        $payload = $this->command->buildNonInteractivePayload($input, 'ALDO', 'Story', 'Full story');

        $this->assertSame('ALDO', $payload['fields']['project']['key']);
        $this->assertSame('Story', $payload['fields']['issuetype']['name']);
        $this->assertSame('Full story', $payload['fields']['summary']);
        $this->assertSame('doc', $payload['fields']['description']['type']);
        $this->assertSame('ALDO-1', $payload['fields']['parent']['key']);
        $this->assertSame('Medium', $payload['fields']['priority']['name']);
        $this->assertSame(['upgrade', 'backend'], $payload['fields']['labels']);
    }

    public function testDryRunOutputsJsonWithoutCallingApi(): void
    {
        $tester = new CommandTester($this->command);

        $tester->execute([
            '--project' => 'TEST',
            '--type' => 'Task',
            '--summary' => 'Dry run ticket',
            '--dry-run' => true,
            '--no-interaction' => true,
        ]);

        $output = $tester->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('TEST', $decoded['fields']['project']['key']);
        $this->assertSame('Task', $decoded['fields']['issuetype']['name']);
        $this->assertSame('Dry run ticket', $decoded['fields']['summary']);
    }

    public function testDryRunReturnsSuccessExitCode(): void
    {
        $tester = new CommandTester($this->command);

        $tester->execute([
            '--project' => 'TEST',
            '--type' => 'Task',
            '--summary' => 'Dry run ticket',
            '--dry-run' => true,
            '--no-interaction' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testNonInteractiveMissingRequiredFlagsReturnsFailure(): void
    {
        $tester = new CommandTester($this->command);

        $tester->execute([
            '--project' => 'TEST',
            '--no-interaction' => true,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
