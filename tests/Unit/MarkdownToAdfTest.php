<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Helpers\MarkdownToAdf;
use PHPUnit\Framework\TestCase;

class MarkdownToAdfTest extends TestCase
{
    public function testWrapsRootAsDoc(): void
    {
        $doc = MarkdownToAdf::convert('Hello');

        self::assertSame('doc', $doc['type']);
        self::assertSame(1, $doc['version']);
        self::assertIsArray($doc['content']);
    }

    public function testPlainSentenceStaysSingleParagraph(): void
    {
        $doc = MarkdownToAdf::convert('Just a simple sentence.');

        self::assertCount(1, $doc['content']);
        self::assertSame('paragraph', $doc['content'][0]['type']);
        self::assertSame('Just a simple sentence.', $doc['content'][0]['content'][0]['text']);
    }

    public function testEmptyStringProducesEmptyParagraph(): void
    {
        $doc = MarkdownToAdf::convert('');

        self::assertCount(1, $doc['content']);
        self::assertSame('paragraph', $doc['content'][0]['type']);
        self::assertSame([], $doc['content'][0]['content']);
    }

    public function testBlankLineSplitsParagraphs(): void
    {
        $doc = MarkdownToAdf::convert("First paragraph.\n\nSecond paragraph.");

        self::assertCount(2, $doc['content']);
        self::assertSame('paragraph', $doc['content'][0]['type']);
        self::assertSame('paragraph', $doc['content'][1]['type']);
    }

    public function testSingleNewlineBecomesHardBreak(): void
    {
        $doc = MarkdownToAdf::convert("Line one\nLine two");

        $nodes = $doc['content'][0]['content'];
        self::assertSame('text', $nodes[0]['type']);
        self::assertSame('hardBreak', $nodes[1]['type']);
        self::assertSame('text', $nodes[2]['type']);
    }

    public function testHeadings(): void
    {
        $doc = MarkdownToAdf::convert("# Title\n\n### Section");

        self::assertSame('heading', $doc['content'][0]['type']);
        self::assertSame(1, $doc['content'][0]['attrs']['level']);
        self::assertSame('Title', $doc['content'][0]['content'][0]['text']);

        self::assertSame('heading', $doc['content'][1]['type']);
        self::assertSame(3, $doc['content'][1]['attrs']['level']);
    }

    public function testBulletList(): void
    {
        $doc = MarkdownToAdf::convert("- one\n- two\n- three");

        self::assertCount(1, $doc['content']);
        $list = $doc['content'][0];
        self::assertSame('bulletList', $list['type']);
        self::assertCount(3, $list['content']);
        self::assertSame('listItem', $list['content'][0]['type']);
        self::assertSame('one', $list['content'][0]['content'][0]['content'][0]['text']);
    }

    public function testOrderedList(): void
    {
        $doc = MarkdownToAdf::convert("1. first\n2. second");

        $list = $doc['content'][0];
        self::assertSame('orderedList', $list['type']);
        self::assertCount(2, $list['content']);
    }

    public function testListThenParagraph(): void
    {
        $doc = MarkdownToAdf::convert("- item\n\nAfter the list.");

        self::assertSame('bulletList', $doc['content'][0]['type']);
        self::assertSame('paragraph', $doc['content'][1]['type']);
        self::assertSame('After the list.', $doc['content'][1]['content'][0]['text']);
    }

    public function testInlineBold(): void
    {
        $doc = MarkdownToAdf::convert('A **bold** word.');

        $nodes = $doc['content'][0]['content'];
        self::assertSame('A ', $nodes[0]['text']);
        self::assertSame('bold', $nodes[1]['text']);
        self::assertSame('strong', $nodes[1]['marks'][0]['type']);
        self::assertSame(' word.', $nodes[2]['text']);
    }

    public function testInlineCodeAndLink(): void
    {
        $doc = MarkdownToAdf::convert('Run `cmd` then see [docs](https://example.com).');

        $nodes = $doc['content'][0]['content'];
        self::assertSame('code', $nodes[1]['marks'][0]['type']);
        self::assertSame('cmd', $nodes[1]['text']);

        $link = $nodes[3];
        self::assertSame('docs', $link['text']);
        self::assertSame('link', $link['marks'][0]['type']);
        self::assertSame('https://example.com', $link['marks'][0]['attrs']['href']);
    }

    public function testCarriageReturnsAreNormalised(): void
    {
        $doc = MarkdownToAdf::convert("First.\r\n\r\nSecond.");

        self::assertCount(2, $doc['content']);
    }
}
