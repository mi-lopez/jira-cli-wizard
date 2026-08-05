<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

/**
 * Turns the comma-separated label input accepted by the CLI into the plain list
 * of strings Jira expects in `fields.labels`.
 */
class LabelParser
{
    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $labels = array_map('trim', explode(',', $raw));
        $labels = array_filter($labels, static fn (string $label): bool => $label !== '');

        return array_values(array_unique($labels));
    }
}
