<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Helpers\AdfToText;
use MiLopez\JiraCliWizard\Helpers\MarkdownToAdf;
use PHPUnit\Framework\TestCase;

class AdfToTextTest extends TestCase
{
    public function testPlainParagraphIsReturnedAsIs(): void
    {
        $doc = MarkdownToAdf::convert('Just a sentence.');

        $this->assertSame('Just a sentence.', AdfToText::convert($doc));
    }

    public function testMarkdownSurvivesTheRoundTrip(): void
    {
        // `view` is only useful if a description written through `create`
        // reads back the way it was typed.
        $markdown = "# Title\n\nSome **bold** and *italic* and `code`.\n\n- one\n- two";

        $this->assertSame($markdown, AdfToText::convert(MarkdownToAdf::convert($markdown)));
    }

    public function testOrderedListsAreNumbered(): void
    {
        $this->assertSame(
            "1. first\n2. second",
            AdfToText::convert(MarkdownToAdf::convert("1. first\n2. second"))
        );
    }

    public function testOrderedListsResumeFromTheirStartAttribute(): void
    {
        // Jira splits a numbered list around an image into two orderedLists and
        // records where the second one resumes; restarting at 1 would be wrong.
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'orderedList',
                'attrs' => ['order' => 3],
                'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'third']]]],
                ]],
            ]],
        ];

        $this->assertSame('3. third', AdfToText::convert($doc));
    }

    public function testLinksKeepTheirHref(): void
    {
        $doc = MarkdownToAdf::convert('See [the docs](https://example.test/doc).');

        $this->assertSame('See [the docs](https://example.test/doc).', AdfToText::convert($doc));
    }

    public function testHardBreaksBecomeNewlines(): void
    {
        $this->assertSame("line one\nline two", AdfToText::convert(MarkdownToAdf::convert("line one\nline two")));
    }

    public function testNestedListsAreIndented(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'bulletList',
                'content' => [[
                    'type' => 'listItem',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'parent']]],
                        ['type' => 'bulletList', 'content' => [[
                            'type' => 'listItem',
                            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'child']]]],
                        ]]],
                    ],
                ]],
            ]],
        ];

        $this->assertSame("- parent\n  - child", AdfToText::convert($doc));
    }

    public function testCodeBlocksKeepTheirFenceAndLanguage(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'codeBlock',
                'attrs' => ['language' => 'php'],
                'content' => [['type' => 'text', 'text' => 'echo 1;']],
            ]],
        ];

        $this->assertSame("```php\necho 1;\n```", AdfToText::convert($doc));
    }

    public function testTaskListsRenderAsCheckboxes(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'taskList',
                'content' => [
                    ['type' => 'taskItem', 'attrs' => ['state' => 'DONE'], 'content' => [['type' => 'text', 'text' => 'done']]],
                    ['type' => 'taskItem', 'attrs' => ['state' => 'TODO'], 'content' => [['type' => 'text', 'text' => 'todo']]],
                ],
            ]],
        ];

        $this->assertSame("- [x] done\n- [ ] todo", AdfToText::convert($doc));
    }

    public function testMentionsAndEmojiKeepTheirText(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'mention', 'attrs' => ['id' => '123', 'text' => '@Ada']],
                    ['type' => 'text', 'text' => ' ships '],
                    ['type' => 'emoji', 'attrs' => ['shortName' => ':rocket:', 'text' => '🚀']],
                ],
            ]],
        ];

        $this->assertSame('@Ada ships 🚀', AdfToText::convert($doc));
    }

    public function testTablesRenderAsPipeRows(): void
    {
        $cell = static fn (string $text) => [
            'type' => 'tableCell',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]],
        ];

        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'table',
                'content' => [
                    ['type' => 'tableRow', 'content' => [$cell('a'), $cell('b')]],
                    ['type' => 'tableRow', 'content' => [$cell('1'), $cell('2')]],
                ],
            ]],
        ];

        $this->assertSame("| a | b |\n| 1 | 2 |", AdfToText::convert($doc));
    }

    public function testAttachmentsAreNamed(): void
    {
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'mediaSingle',
                'content' => [['type' => 'media', 'attrs' => ['id' => 'abc', 'alt' => 'screenshot.png']]],
            ]],
        ];

        $this->assertSame('[attachment: screenshot.png]', AdfToText::convert($doc));
    }

    public function testUnknownNodesStillRenderTheirText(): void
    {
        // ADF grows over time; a node we do not know must lose its decoration,
        // never the words inside it.
        $doc = [
            'type' => 'doc',
            'version' => 1,
            'content' => [[
                'type' => 'someFutureNode',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'still here']]]],
            ]],
        ];

        $this->assertSame('still here', AdfToText::convert($doc));
    }

    public function testStringDescriptionsFromApiV2AreAccepted(): void
    {
        $this->assertSame('legacy text', AdfToText::convert('  legacy text  '));
    }

    public function testNullDescriptionBecomesAnEmptyString(): void
    {
        $this->assertSame('', AdfToText::convert(null));
    }
}
