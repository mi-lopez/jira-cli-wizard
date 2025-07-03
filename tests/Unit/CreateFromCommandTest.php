<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CreateFromCommandTest extends TestCase
{
    public function testCommandIsConfiguredProperly(): void
    {
        $command = new \MiLopez\JiraCliWizard\Commands\CreateFromCommand();

        $this->assertEquals('create-from', $command->getName());
        $this->assertEquals('Create a new ticket based on an existing one', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('issue-key'));
        $this->assertTrue($command->getDefinition()->hasOption('project'));
    }
}
