<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\ListCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ListCommandTest extends TestCase
{
    private ListCommand $command;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new ListCommand();

        $app = new Application();
        $app->add($this->command);

        $this->tester = new CommandTester($this->command);
    }

    public function testCommandIsConfiguredProperly(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('list', $this->command->getName());
        $this->assertTrue($definition->hasArgument('resource'));
        $this->assertTrue($definition->hasOption('project'));
    }

    public function testHelpTextMentionsAllResources(): void
    {
        $help = $this->command->getHelp();

        foreach (ListCommand::RESOURCES as $resource) {
            $this->assertStringContainsString($resource, $help);
        }
    }

    public function testNoArgumentPrintsAvailableResourcesAndSucceeds(): void
    {
        $this->tester->execute([]);

        $output = $this->tester->getDisplay();

        $this->assertSame(0, $this->tester->getStatusCode());

        foreach (ListCommand::RESOURCES as $resource) {
            $this->assertStringContainsString($resource, $output);
        }
    }

    public function testUnknownResourceReturnsFailure(): void
    {
        $this->tester->execute(['resource' => 'unknown-resource']);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('Unknown resource', $this->tester->getDisplay());
    }

    public function testUnknownResourceErrorMentionsValidResources(): void
    {
        $this->tester->execute(['resource' => 'foo']);

        $output = $this->tester->getDisplay();

        foreach (ListCommand::RESOURCES as $resource) {
            $this->assertStringContainsString($resource, $output);
        }
    }

    public function testAllResourcesAreDeclared(): void
    {
        $this->assertSame(['projects', 'issue-types', 'priorities', 'epics', 'sprints'], ListCommand::RESOURCES);
    }
}
