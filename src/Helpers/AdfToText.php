<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

/**
 * Renders an Atlassian Document Format (ADF) document as lightweight Markdown.
 *
 * Jira Cloud's REST API v3 returns rich text as an ADF tree, which is useless
 * to read in a terminal. This is the counterpart of {@see MarkdownToAdf}: it
 * walks the tree back into the same Markdown flavour the wizard accepts as
 * input, so a description written through `create` renders as it was typed and
 * a description written in the Jira UI degrades to something readable.
 *
 * Unknown node types are not dropped: their children are still rendered, so a
 * future ADF node only loses its decoration, never its text.
 */
final class AdfToText
{
    /**
     * @param array<string, mixed>|string|null $document ADF doc node, or a raw
     *                                                   string for the API v2
     *                                                   shape / already-plain text
     */
    public static function convert($document): string
    {
        if (is_string($document)) {
            return trim($document);
        }

        if (!is_array($document)) {
            return '';
        }

        return trim(self::blocks(self::children($document)));
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private static function blocks(array $nodes, int $depth = 0): string
    {
        $rendered = [];

        foreach ($nodes as $node) {
            $block = self::block($node, $depth);
            if (trim($block) !== '') {
                $rendered[] = $block;
            }
        }

        return implode("\n\n", $rendered);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function block(array $node, int $depth): string
    {
        $type = $node['type'] ?? '';
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        switch ($type) {
            case 'paragraph':
                return self::inline(self::children($node));

            case 'heading':
                $level = (int) ($attrs['level'] ?? 1);

                return str_repeat('#', max(1, min(6, $level))) . ' ' . self::inline(self::children($node));

            case 'bulletList':
                return self::list($node, false, $depth);

            case 'orderedList':
                // A list that continues after an image or a paragraph comes back
                // as a second orderedList carrying the number it resumes from.
                return self::list($node, true, $depth, (int) ($attrs['order'] ?? 1));

            case 'taskList':
                return self::taskList($node, $depth);

            case 'codeBlock':
                $language = is_string($attrs['language'] ?? null) ? $attrs['language'] : '';

                return "```{$language}\n" . self::inline(self::children($node)) . "\n```";

            case 'blockquote':
                return self::prefixLines(self::blocks(self::children($node), $depth), '> ');

            case 'panel':
                // Panels carry their kind in attrs; keep it visible since the
                // colour it renders with in the UI is lost in a terminal.
                $kind = is_string($attrs['panelType'] ?? null) ? strtoupper($attrs['panelType']) : 'NOTE';

                return self::prefixLines("[{$kind}]\n" . self::blocks(self::children($node), $depth), '> ');

            case 'rule':
                return '---';

            case 'mediaSingle':
            case 'mediaGroup':
                return self::media($node);

            case 'table':
                return self::table($node);

            case 'expand':
            case 'nestedExpand':
                $title = is_string($attrs['title'] ?? null) && $attrs['title'] !== '' ? $attrs['title'] : 'Details';

                return "▸ {$title}\n" . self::blocks(self::children($node), $depth);

            default:
                $children = self::children($node);

                return $children === [] ? self::inline([$node]) : self::blocks($children, $depth);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function list(array $node, bool $ordered, int $depth, int $start = 1): string
    {
        $indent = str_repeat('  ', $depth);
        $lines = [];
        $number = max(1, $start);

        foreach (self::children($node) as $item) {
            $marker = $ordered ? $number . '. ' : '- ';
            $lines[] = self::listItem($item, $indent . $marker, $depth);
            ++$number;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function taskList(array $node, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $lines = [];

        foreach (self::children($node) as $item) {
            $attrs = is_array($item['attrs'] ?? null) ? $item['attrs'] : [];
            $done = ($attrs['state'] ?? '') === 'DONE';
            $marker = $indent . ($done ? '- [x] ' : '- [ ] ');
            $lines[] = $marker . self::inline(self::children($item));
        }

        return implode("\n", $lines);
    }

    /**
     * Renders one list item: its first paragraph sits on the marker line, any
     * nested list or extra block continues underneath it.
     *
     * @param array<string, mixed> $item
     */
    private static function listItem(array $item, string $marker, int $depth): string
    {
        $parts = [];

        foreach (self::children($item) as $child) {
            $type = $child['type'] ?? '';

            if (in_array($type, ['bulletList', 'orderedList', 'taskList'], true)) {
                $parts[] = self::block($child, $depth + 1);
                continue;
            }

            $parts[] = self::block($child, $depth);
        }

        $body = implode("\n", array_filter($parts, static fn (string $part) => trim($part) !== ''));
        $lines = explode("\n", $body);
        $first = array_shift($lines);

        $rendered = $marker . $first;

        foreach ($lines as $line) {
            // Continuation lines already carry their own indent when they come
            // from a nested list; plain ones get aligned under the marker.
            $rendered .= "\n" . (str_starts_with($line, ' ') ? $line : str_repeat(' ', strlen($marker)) . $line);
        }

        return $rendered;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function media(array $node): string
    {
        $names = [];

        foreach (self::children($node) as $child) {
            if (($child['type'] ?? '') !== 'media') {
                continue;
            }
            $attrs = is_array($child['attrs'] ?? null) ? $child['attrs'] : [];
            $names[] = is_string($attrs['alt'] ?? null) && $attrs['alt'] !== ''
                ? $attrs['alt']
                : (is_string($attrs['id'] ?? null) ? $attrs['id'] : 'file');
        }

        return $names === [] ? '[attachment]' : '[attachment: ' . implode(', ', $names) . ']';
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function table(array $node): string
    {
        $rows = [];

        foreach (self::children($node) as $row) {
            if (($row['type'] ?? '') !== 'tableRow') {
                continue;
            }

            $cells = [];
            foreach (self::children($row) as $cell) {
                $cells[] = str_replace("\n", ' ', trim(self::blocks(self::children($cell))));
            }

            $rows[] = '| ' . implode(' | ', $cells) . ' |';
        }

        return implode("\n", $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private static function inline(array $nodes): string
    {
        $text = '';

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

            switch ($type) {
                case 'text':
                    $text .= self::marks(is_string($node['text'] ?? null) ? $node['text'] : '', $node);
                    break;

                case 'hardBreak':
                    $text .= "\n";
                    break;

                case 'mention':
                    $text .= is_string($attrs['text'] ?? null) ? $attrs['text'] : '@unknown';
                    break;

                case 'emoji':
                    $text .= is_string($attrs['text'] ?? null) ? $attrs['text'] : (is_string($attrs['shortName'] ?? null) ? $attrs['shortName'] : '');
                    break;

                case 'inlineCard':
                    $text .= is_string($attrs['url'] ?? null) ? $attrs['url'] : '';
                    break;

                case 'status':
                    $text .= '[' . strtoupper(is_string($attrs['text'] ?? null) ? $attrs['text'] : '') . ']';
                    break;

                case 'date':
                    $timestamp = (int) ($attrs['timestamp'] ?? 0);
                    $text .= $timestamp > 0 ? date('Y-m-d', (int) ($timestamp / 1000)) : '';
                    break;

                default:
                    $text .= self::inline(self::children($node));
            }
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function marks(string $text, array $node): string
    {
        if ($text === '') {
            return '';
        }

        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];

        foreach ($marks as $mark) {
            $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];

            switch ($mark['type'] ?? '') {
                case 'code':
                    $text = '`' . $text . '`';
                    break;
                case 'strong':
                    $text = '**' . $text . '**';
                    break;
                case 'em':
                    $text = '*' . $text . '*';
                    break;
                case 'strike':
                    $text = '~~' . $text . '~~';
                    break;
                case 'link':
                    $href = is_string($attrs['href'] ?? null) ? $attrs['href'] : '';
                    $text = $href === '' ? $text : '[' . $text . '](' . $href . ')';
                    break;
            }
        }

        return $text;
    }

    private static function prefixLines(string $text, string $prefix): string
    {
        $lines = array_map(
            static fn (string $line) => $prefix . $line,
            explode("\n", $text)
        );

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<int, array<string, mixed>>
     */
    private static function children(array $node): array
    {
        $content = $node['content'] ?? null;

        if (!is_array($content)) {
            return [];
        }

        return array_values(array_filter($content, static fn ($child) => is_array($child)));
    }
}
