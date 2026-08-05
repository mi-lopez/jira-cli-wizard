<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Helpers\LabelParser;
use PHPUnit\Framework\TestCase;

class LabelParserTest extends TestCase
{
    public function testParsesCommaSeparatedLabels(): void
    {
        $this->assertSame(['backend', 'upgrade'], LabelParser::parse('backend,upgrade'));
    }

    public function testTrimsSurroundingWhitespace(): void
    {
        $this->assertSame(['backend', 'upgrade'], LabelParser::parse('  backend ,  upgrade  '));
    }

    public function testDropsEmptySegments(): void
    {
        $this->assertSame(['backend', 'upgrade'], LabelParser::parse('backend,,upgrade,'));
    }

    public function testRemovesDuplicates(): void
    {
        $this->assertSame(['backend', 'upgrade'], LabelParser::parse('backend,upgrade,backend'));
    }

    public function testReturnsListWithSequentialKeysAfterFiltering(): void
    {
        // array_filter and array_unique both preserve keys; callers push this
        // straight into a JSON payload, where gaps would serialise as an object
        // instead of an array and Jira would reject it.
        $labels = LabelParser::parse('a,,b,a,c');

        $this->assertSame([0, 1, 2], array_keys($labels));
        $this->assertSame('["a","b","c"]', json_encode($labels));
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $this->assertSame([], LabelParser::parse(''));
        $this->assertSame([], LabelParser::parse('   '));
        $this->assertSame([], LabelParser::parse(',,,'));
    }

    public function testLabelsAreCaseSensitiveAsJiraTreatsThem(): void
    {
        $this->assertSame(['Backend', 'backend'], LabelParser::parse('Backend,backend'));
    }
}
