<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Helpers\AdfHelper;
use PHPUnit\Framework\TestCase;

class AdfHelperTest extends TestCase
{
    public function testEmptyDescriptionProducesEmptyDoc(): void
    {
        $doc = AdfHelper::descriptionToDoc('');

        $this->assertSame('doc', $doc['type']);
        $this->assertSame([], $doc['content']);
    }

    public function testParagraphIsParsed(): void
    {
        $doc = AdfHelper::descriptionToDoc('Just a line');

        $this->assertSame('paragraph', $doc['content'][0]['type']);
        $this->assertSame('Just a line', $doc['content'][0]['content'][0]['text']);
    }

    public function testMarkdownHeadingIsParsed(): void
    {
        $doc = AdfHelper::descriptionToDoc('## Problem');

        $this->assertSame('heading', $doc['content'][0]['type']);
        $this->assertSame(2, $doc['content'][0]['attrs']['level']);
        $this->assertSame('Problem', $doc['content'][0]['content'][0]['text']);
    }

    public function testHeadingLevelsOneToSix(): void
    {
        foreach (range(1, 6) as $level) {
            $doc = AdfHelper::descriptionToDoc(str_repeat('#', $level) . ' Title');
            $this->assertSame('heading', $doc['content'][0]['type']);
            $this->assertSame($level, $doc['content'][0]['attrs']['level']);
        }
    }

    public function testSevenHashesIsNotAHeading(): void
    {
        $doc = AdfHelper::descriptionToDoc('####### Too deep');

        $this->assertSame('paragraph', $doc['content'][0]['type']);
        $this->assertSame('####### Too deep', $doc['content'][0]['content'][0]['text']);
    }

    public function testHeadingFlushesPendingBullets(): void
    {
        $doc = AdfHelper::descriptionToDoc("- one\n- two\n## Next");

        $this->assertSame('bulletList', $doc['content'][0]['type']);
        $this->assertCount(2, $doc['content'][0]['content']);
        $this->assertSame('heading', $doc['content'][1]['type']);
    }

    public function testHeadingSupportsInlineBold(): void
    {
        $doc = AdfHelper::descriptionToDoc('# A **bold** heading');

        $marks = $doc['content'][0]['content'][1]['marks'] ?? [];
        $this->assertSame('strong', $marks[0]['type']);
    }

    public function testMixedDocumentStructure(): void
    {
        $doc = AdfHelper::descriptionToDoc("## Heading\nFirst line\n\n- bullet one\n- bullet two\n\nSome **bold** text.");

        $types = array_map(static fn (array $n): string => $n['type'], $doc['content']);
        $this->assertSame(['heading', 'paragraph', 'bulletList', 'paragraph'], $types);
    }
}
