<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

class AdfHelper
{
    public static function descriptionToDoc(string $description): array
    {
        $description = str_replace(["\r\n", "\r"], "\n", trim($description));

        if ($description === '') {
            return [
                'type' => 'doc',
                'version' => 1,
                'content' => [],
            ];
        }

        $content = [];
        $bulletItems = [];

        foreach (explode("\n", $description) as $line) {
            $line = trim($line);

            if ($line === '') {
                self::flushBulletItems($content, $bulletItems);
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches) === 1) {
                $bulletItems[] = [
                    'type' => 'listItem',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => self::parseInline($matches[1]),
                        ],
                    ],
                ];
                continue;
            }

            self::flushBulletItems($content, $bulletItems);
            $content[] = [
                'type' => 'paragraph',
                'content' => self::parseInline($line),
            ];
        }

        self::flushBulletItems($content, $bulletItems);

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $content,
        ];
    }

    private static function flushBulletItems(array &$content, array &$bulletItems): void
    {
        if ($bulletItems === []) {
            return;
        }

        $content[] = [
            'type' => 'bulletList',
            'content' => $bulletItems,
        ];
        $bulletItems = [];
    }

    private static function parseInline(string $text): array
    {
        $nodes = [];
        $parts = preg_split('/(\*\*.+?\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($parts ?: [] as $part) {
            if (str_starts_with($part, '**') && str_ends_with($part, '**') && strlen($part) > 4) {
                $nodes[] = [
                    'type' => 'text',
                    'text' => substr($part, 2, -2),
                    'marks' => [
                        ['type' => 'strong'],
                    ],
                ];
                continue;
            }

            $nodes[] = [
                'type' => 'text',
                'text' => $part,
            ];
        }

        return $nodes !== [] ? $nodes : [['type' => 'text', 'text' => $text]];
    }
}
