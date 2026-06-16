<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Commands\UpdateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

class UpdateCommandTest extends TestCase
{
    private UpdateCommand $command;

    protected function setUp(): void
    {
        $this->command = new UpdateCommand();

        $app = new Application();
        $app->add($this->command);
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('key'));
        $this->assertTrue($definition->hasOption('summary'));
        $this->assertTrue($definition->hasOption('description'));
        $this->assertTrue($definition->hasOption('parent'));
        $this->assertTrue($definition->hasOption('epic'));
        $this->assertTrue($definition->hasOption('labels'));
        $this->assertTrue($definition->hasOption('priority'));
        $this->assertTrue($definition->hasOption('add-to-sprint'));
        $this->assertTrue($definition->hasOption('comment'));
        $this->assertTrue($definition->hasOption('dry-run'));
    }

    public function testBuildFieldsEmptyWhenNoOptions(): void
    {
        $input = new ArrayInput(['key' => 'CAM-1'], $this->command->getDefinition());

        $this->assertSame([], $this->command->buildFields($input));
    }

    public function testBuildFieldsOnlyIncludesProvidedOptions(): void
    {
        $input = new ArrayInput([
            'key' => 'CAM-1755',
            '--summary' => 'Corrected summary',
            '--priority' => 'Low',
        ], $this->command->getDefinition());

        $fields = $this->command->buildFields($input);

        $this->assertSame('Corrected summary', $fields['summary']);
        $this->assertSame('Low', $fields['priority']['name']);
        $this->assertArrayNotHasKey('description', $fields);
        $this->assertArrayNotHasKey('parent', $fields);
        $this->assertArrayNotHasKey('labels', $fields);
    }

    public function testBuildFieldsDescriptionBecomesAdfDoc(): void
    {
        $input = new ArrayInput([
            'key' => 'CAM-1',
            '--description' => '## Heading',
        ], $this->command->getDefinition());

        $fields = $this->command->buildFields($input);

        $this->assertSame('doc', $fields['description']['type']);
        $this->assertSame('heading', $fields['description']['content'][0]['type']);
    }

    public function testBuildFieldsEpicMapsToParentUppercased(): void
    {
        $input = new ArrayInput([
            'key' => 'CAM-1',
            '--epic' => 'cam-1075',
        ], $this->command->getDefinition());

        $fields = $this->command->buildFields($input);

        $this->assertSame('CAM-1075', $fields['parent']['key']);
    }

    public function testBuildFieldsParsesLabels(): void
    {
        $input = new ArrayInput([
            'key' => 'CAM-1',
            '--labels' => 'backend, upgrade, backend',
        ], $this->command->getDefinition());

        $fields = $this->command->buildFields($input);

        $this->assertSame(['backend', 'upgrade'], $fields['labels']);
    }
}
