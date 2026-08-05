<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

/**
 * Converts a plain-text / lightweight Markdown description into an
 * Atlassian Document Format (ADF) document.
 *
 * Jira Cloud's REST API v3 stores rich text as ADF, not as wiki markup.
 * Passing a raw string wrapped in a single paragraph means blank lines,
 * headings, lists and bold are rendered literally. This converter turns
 * the common Markdown constructs into proper ADF nodes so the ticket
 * reads as intended.
 *
 * Supported block syntax:
 *   - `#` .. `######`  headings (level 1-6)
 *   - `-`, `*`, `+`    bullet lists
 *   - `1.`, `1)`       ordered lists
 *   - blank line       paragraph separator
 * Supported inline syntax: **bold**, *italic* / _italic_, `code`,
 * [text](url). Plain text is preserved verbatim, so existing callers
 * that pass a simple sentence keep working unchanged.
 */
final class MarkdownToAdf
{
    /**
     * @return array{type: string, version: int, content: array<int, array<string, mixed>>}
     */
    public static function convert(string $text): array
    {
        $content = self::blocks($text);

        if ($content === []) {
            // ADF requires at least one node; keep an empty paragraph.
            $content = [['type' => 'paragraph', 'content' => []]];
        }

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $content,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function blocks(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $content = [];
        $paragraph = [];

        $count = count($lines);
        for ($i = 0; $i < $count; ++$i) {
            $line = $lines[$i];

            if (trim($line) === '') {
                self::flushParagraph($content, $paragraph);
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $m) === 1) {
                self::flushParagraph($content, $paragraph);
                $content[] = [
                    'type' => 'heading',
                    'attrs' => ['level' => strlen($m[1])],
                    'content' => self::inline($m[2]),
                ];
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.+)$/', $line) === 1) {
                self::flushParagraph($content, $paragraph);
                [$content[], $i] = self::list($lines, $i, $count, 'bullet');
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line) === 1) {
                self::flushParagraph($content, $paragraph);
                [$content[], $i] = self::list($lines, $i, $count, 'ordered');
                continue;
            }

            $paragraph[] = $line;
        }

        self::flushParagraph($content, $paragraph);

        return $content;
    }

    /**
     * Appends the collected lines as a paragraph node and resets the buffer.
     *
     * @param array<int, array<string, mixed>> $content
     * @param array<int, string>               $paragraph
     */
    private static function flushParagraph(array &$content, array &$paragraph): void
    {
        if ($paragraph === []) {
            return;
        }

        $nodes = [];
        foreach ($paragraph as $index => $line) {
            if ($index > 0) {
                $nodes[] = ['type' => 'hardBreak'];
            }
            foreach (self::inline($line) as $node) {
                $nodes[] = $node;
            }
        }

        $content[] = ['type' => 'paragraph', 'content' => $nodes];
        $paragraph = [];
    }

    /**
     * Consumes consecutive list items starting at $start.
     *
     * @param array<int, string> $lines
     *
     * @return array{0: array<string, mixed>, 1: int} the list node and the index of the last consumed line
     */
    private static function list(array $lines, int $start, int $count, string $kind): array
    {
        $pattern = $kind === 'ordered' ? '/^\s*\d+[.)]\s+(.+)$/' : '/^\s*[-*+]\s+(.+)$/';
        $items = [];
        $i = $start;

        for (; $i < $count; ++$i) {
            if (preg_match($pattern, $lines[$i], $m) !== 1) {
                break;
            }
            $items[] = [
                'type' => 'listItem',
                'content' => [['type' => 'paragraph', 'content' => self::inline($m[1])]],
            ];
        }

        $node = [
            'type' => $kind === 'ordered' ? 'orderedList' : 'bulletList',
            'content' => $items,
        ];

        return [$node, $i - 1];
    }

    /**
     * Parses inline marks (bold, italic, code, link) into ADF text nodes.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function inline(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $patterns = [
            ['re' => '/`([^`]+)`/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'code']]]],
            ['re' => '/\*\*([^*]+)\*\*/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'strong']]]],
            ['re' => '/__([^_]+)__/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'strong']]]],
            ['re' => '/(?<!\*)\*([^*\s][^*]*)\*(?!\*)/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'em']]]],
            ['re' => '/_([^_]+)_/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'em']]]],
            ['re' => '/\[([^\]]+)\]\(([^)]+)\)/', 'mark' => static fn (array $m) => [$m[1], [['type' => 'link', 'attrs' => ['href' => $m[2]]]]]],
        ];

        $nodes = [];
        $cursor = 0;
        $length = strlen($text);

        while ($cursor < $length) {
            $best = null;
            $bestPos = $length;
            $bestMatch = null;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern['re'], $text, $m, PREG_OFFSET_CAPTURE, $cursor) === 1) {
                    $pos = $m[0][1];
                    if ($pos < $bestPos) {
                        $bestPos = $pos;
                        $best = $pattern;
                        $bestMatch = $m;
                    }
                }
            }

            if ($best === null || $bestMatch === null) {
                $nodes[] = self::textNode(substr($text, $cursor));
                break;
            }

            if ($bestPos > $cursor) {
                $nodes[] = self::textNode(substr($text, $cursor, $bestPos - $cursor));
            }

            $plainMatch = array_map(static fn ($group) => $group[0], $bestMatch);
            [$value, $marks] = $best['mark']($plainMatch);
            $nodes[] = self::textNode($value, $marks);

            $cursor = $bestPos + strlen($bestMatch[0][0]);
        }

        return $nodes;
    }

    /**
     * @param array<int, array<string, mixed>>|null $marks
     *
     * @return array<string, mixed>
     */
    private static function textNode(string $text, ?array $marks = null): array
    {
        $node = ['type' => 'text', 'text' => $text];
        if ($marks !== null && $marks !== []) {
            $node['marks'] = $marks;
        }

        return $node;
    }
}
