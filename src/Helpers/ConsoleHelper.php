<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

use Symfony\Component\Console\Output\OutputInterface;

class ConsoleHelper
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function title(string $title): void
    {
        $this->output->writeln('');
        $this->output->writeln("<fg=cyan;options=bold>{$title}</fg=cyan;options=bold>");
        $this->output->writeln('<fg=cyan>' . str_repeat('=', strlen($title)) . '</fg=cyan>');
        $this->output->writeln('');
    }

    public function step(string $step, string $description): void
    {
        $this->output->writeln('');
        $this->output->writeln("<fg=blue;options=bold>[{$step}] {$description}</fg=blue;options=bold>");
        $this->output->writeln('<fg=blue>' . str_repeat('-', strlen("[{$step}] {$description}")) . '</fg=blue>');
    }

    public function success(string $message): void
    {
        $this->output->writeln("<fg=green>{$message}</fg=green>");
    }

    public function error(string $message): void
    {
        $this->output->writeln("<fg=red>{$message}</fg=red>");
    }

    public function warning(string $message): void
    {
        $this->output->writeln("<fg=yellow>{$message}</fg=yellow>");
    }

    public function info(string $message): void
    {
        $this->output->writeln("<fg=blue>{$message}</fg=blue>");
    }

    public function separator(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<fg=gray>' . str_repeat('─', 50) . '</fg=gray>');
        $this->output->writeln('');
    }

    public function banner(): void
    {
        $banner = [
            '     _____ _            ',
            '    |_   _(_)           ',
            '      | |  _ _ __ __ _   ',
            '      | | | |  __/ _` |  ',
            '     _| |_| | | | (_| |  ',
            '    |_____|_|_|  \__,_|  ',
            '                         ',
            '    🎯 CLI Wizard for Jira',
            '',
        ];

        foreach ($banner as $line) {
            $this->output->writeln("<fg=cyan>{$line}</fg=cyan>");
        }
    }

    public function box(array $lines, string $color = 'white'): void
    {
        $maxLength = max(array_map('strlen', $lines));
        $border = str_repeat('─', $maxLength + 4);

        $this->output->writeln("<fg={$color}>┌{$border}┐</fg={$color}>");

        foreach ($lines as $line) {
            $padding = str_repeat(' ', $maxLength - strlen($line));
            $this->output->writeln("<fg={$color}>│  {$line}{$padding}  │</fg={$color}>");
        }

        $this->output->writeln("<fg={$color}>└{$border}┘</fg={$color}>");
    }

    public function progressBar(int $current, int $total, string $label = ''): void
    {
        $percentage = round(($current / $total) * 100);
        $filled = (int) round(($current / $total) * 20);
        $empty = 20 - $filled;

        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
        $this->output->write("\r<fg=green>[{$bar}]</fg=green> {$percentage}% {$label}");

        if ($current === $total) {
            $this->output->writeln('');
        }
    }
}
