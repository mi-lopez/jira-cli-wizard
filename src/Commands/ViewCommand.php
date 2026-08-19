<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\AdfToText;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'view', description: 'Show an existing Jira ticket in the terminal')]
class ViewCommand extends Command
{
    private JiraApiClient $jiraClient;

    private ?JiraApiClient $injectedClient;

    /**
     * @param JiraApiClient|null $jiraClient injected by tests; production callers
     *                                      leave it null and the client is built
     *                                      from config inside execute()
     */
    public function __construct(?JiraApiClient $jiraClient = null)
    {
        parent::__construct();
        $this->injectedClient = $jiraClient;

        if ($jiraClient !== null) {
            $this->jiraClient = $jiraClient;
        }
    }

    protected function configure(): void
    {
        $this
            ->setName('view')
            ->setDescription('Show an existing Jira ticket in the terminal')
            ->addArgument(
                'issue-key',
                InputArgument::REQUIRED,
                'The issue key to display (e.g., ALDO-1234)'
            )
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the ticket as JSON (for scripting/AI agents)')
            ->addOption('comments', 'c', InputOption::VALUE_NONE, 'Also fetch and show the ticket comments')
            ->setHelp(
                'Reads a ticket and prints its fields plus the description, converting Jira\'s' . PHP_EOL .
                'rich text back to the same Markdown flavour `create` and `update` accept.' . PHP_EOL . PHP_EOL .
                'Examples:' . PHP_EOL .
                '  jira-wizard view ALDO-1234' . PHP_EOL .
                '  jira-wizard view ALDO-1234 --comments' . PHP_EOL .
                '  jira-wizard view aldo-1234 --json' . PHP_EOL . PHP_EOL .
                'Comments live behind their own endpoint, so they cost an extra request and' . PHP_EOL .
                'are only fetched when --comments is passed.' . PHP_EOL . PHP_EOL .
                '--json prints a flattened summary of the ticket, not Jira\'s raw payload:' . PHP_EOL .
                'the API response carries the changelog and every rendered field, which is' . PHP_EOL .
                'noise for a script that just wants the ticket.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $issueKey = self::normalizeKey((string) $input->getArgument('issue-key'));

        if ($issueKey === null) {
            $output->writeln('<fg=red>❌ Invalid issue key. Expected something like ALDO-1234.</fg=red>');

            return Command::FAILURE;
        }

        $config = new ConfigManager();

        if (!$config->isConfigured()) {
            $output->writeln('<fg=red>Jira CLI is not configured. Please run: jira-wizard configure</fg=red>');

            return Command::FAILURE;
        }

        $jiraUrl = $config->get('jira_url');
        $jiraEmail = $config->get('jira_email');
        $jiraToken = $config->get('jira_token');

        if (!$jiraUrl || !$jiraEmail || !$jiraToken) {
            $output->writeln('<fg=red>❌ Missing configuration values</fg=red>');

            return Command::FAILURE;
        }

        $this->jiraClient = $this->injectedClient ?? new JiraApiClient($jiraUrl, $jiraEmail, $jiraToken);

        $issue = $this->jiraClient->getIssue($issueKey);

        if (!$issue) {
            $output->writeln("<fg=red>❌ Issue {$issueKey} not found or inaccessible.</fg=red>");

            return Command::FAILURE;
        }

        $comments = $input->getOption('comments')
            ? $this->jiraClient->getIssueComments($issueKey)
            : null;

        if ($input->getOption('json')) {
            $output->write(json_encode(
                self::toArray($issue, $jiraUrl, $comments),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));

            return Command::SUCCESS;
        }

        $this->renderIssue($output, $issue, $jiraUrl, $comments);

        return Command::SUCCESS;
    }

    /**
     * Uppercases the key so `jira view aldo-1234` works, and rejects anything
     * that is not a key before spending a round trip on the API.
     */
    public static function normalizeKey(string $raw): ?string
    {
        $key = strtoupper(trim($raw));

        return preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $key) === 1 ? $key : null;
    }

    /**
     * @param array<string, mixed>                  $issue
     * @param array<int, array<string, mixed>>|null $comments null when they were not requested
     */
    public function renderIssue(OutputInterface $output, array $issue, string $baseUrl = '', ?array $comments = null): void
    {
        $fields = is_array($issue['fields'] ?? null) ? $issue['fields'] : [];
        $key = is_string($issue['key'] ?? null) ? $issue['key'] : '';
        $summary = OutputFormatter::escape(self::stringField($fields, 'summary'));

        $output->writeln('');
        $output->writeln("<fg=cyan;options=bold>{$key}</fg=cyan;options=bold> <options=bold>{$summary}</options=bold>");
        $output->writeln('<fg=gray>' . str_repeat('─', 60) . '</fg=gray>');

        foreach ($this->metaRows($fields, $key, $baseUrl) as $label => $value) {
            $output->writeln(sprintf('  <fg=blue>%-10s</fg=blue> %s', $label, OutputFormatter::escape($value)));
        }

        $description = AdfToText::convert($fields['description'] ?? null);

        $output->writeln('');
        if ($description === '') {
            $output->writeln('<fg=gray>(no description)</fg=gray>');
        } else {
            $output->writeln('<fg=blue;options=bold>Description</fg=blue;options=bold>');
            $output->writeln('');
            $output->writeln(OutputFormatter::escape($description));
        }

        if ($comments !== null) {
            $this->renderComments($output, $comments);
        }

        $output->writeln('');
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     */
    private function renderComments(OutputInterface $output, array $comments): void
    {
        $output->writeln('');

        if ($comments === []) {
            $output->writeln('<fg=gray>(no comments)</fg=gray>');

            return;
        }

        $output->writeln('<fg=blue;options=bold>Comments (' . count($comments) . ')</fg=blue;options=bold>');

        foreach ($comments as $comment) {
            $author = self::nestedField($comment, 'author', 'displayName');
            $created = self::date(self::stringField($comment, 'created'));
            $updated = self::date(self::stringField($comment, 'updated'));
            // Jira sets updated to created on untouched comments; only a real
            // edit is worth the extra words.
            $edited = $updated !== '' && $updated !== $created ? " (edited {$updated})" : '';

            $output->writeln('');
            $output->writeln(OutputFormatter::escape(
                '  ' . ($author !== '' ? $author : 'Unknown') . ' · ' . $created . $edited
            ));

            $body = AdfToText::convert($comment['body'] ?? null);

            foreach (explode("\n", $body === '' ? '(empty)' : $body) as $line) {
                $output->writeln(OutputFormatter::escape('  ' . $line));
            }
        }
    }

    /**
     * Flattens the issue into the shape `list` uses for its own resources:
     * only the fields a caller asked to see, with the description already
     * converted to Markdown.
     *
     * @param array<string, mixed>                  $issue
     * @param array<int, array<string, mixed>>|null $comments null when they were
     *                                                        not requested, which
     *                                                        is not the same as a
     *                                                        ticket having none
     *
     * @return array<string, mixed>
     */
    public static function toArray(array $issue, string $baseUrl = '', ?array $comments = null): array
    {
        $fields = is_array($issue['fields'] ?? null) ? $issue['fields'] : [];
        $key = is_string($issue['key'] ?? null) ? $issue['key'] : '';

        return [
            'key' => $key,
            'url' => $baseUrl !== '' && $key !== '' ? rtrim($baseUrl, '/') . '/browse/' . $key : null,
            'summary' => self::stringField($fields, 'summary'),
            'status' => self::nullIfEmpty(self::nestedField($fields, 'status', 'name')),
            'type' => self::nullIfEmpty(self::nestedField($fields, 'issuetype', 'name')),
            'priority' => self::nullIfEmpty(self::nestedField($fields, 'priority', 'name')),
            'assignee' => self::nullIfEmpty(self::nestedField($fields, 'assignee', 'displayName')),
            'reporter' => self::nullIfEmpty(self::nestedField($fields, 'reporter', 'displayName')),
            'project' => self::nullIfEmpty(self::nestedField($fields, 'project', 'key')),
            'parent' => self::nullIfEmpty(self::nestedField($fields, 'parent', 'key')),
            'labels' => array_values(array_filter(
                is_array($fields['labels'] ?? null) ? $fields['labels'] : [],
                static fn ($label) => is_string($label)
            )),
            'created' => self::nullIfEmpty(self::stringField($fields, 'created')),
            'updated' => self::nullIfEmpty(self::stringField($fields, 'updated')),
            'description' => AdfToText::convert($fields['description'] ?? null),
        ] + ($comments === null ? [] : ['comments' => array_map(
            static fn (array $comment) => [
                'author' => self::nestedField($comment, 'author', 'displayName'),
                'created' => self::nullIfEmpty(self::stringField($comment, 'created')),
                'updated' => self::nullIfEmpty(self::stringField($comment, 'updated')),
                'body' => AdfToText::convert($comment['body'] ?? null),
            ],
            $comments
        )]);
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * The fields worth showing, in reading order, skipping the ones the ticket
     * does not carry so an empty value never takes up a line.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, string>
     */
    private function metaRows(array $fields, string $key, string $baseUrl): array
    {
        $rows = [
            'Status' => self::nestedField($fields, 'status', 'name'),
            'Type' => self::nestedField($fields, 'issuetype', 'name'),
            'Priority' => self::nestedField($fields, 'priority', 'name'),
            'Assignee' => self::nestedField($fields, 'assignee', 'displayName'),
            'Reporter' => self::nestedField($fields, 'reporter', 'displayName'),
            'Project' => self::nestedField($fields, 'project', 'key'),
            'Parent' => self::parentLabel($fields),
            'Labels' => implode(', ', array_filter(
                is_array($fields['labels'] ?? null) ? $fields['labels'] : [],
                static fn ($label) => is_string($label)
            )),
            'Created' => self::date(self::stringField($fields, 'created')),
            'Updated' => self::date(self::stringField($fields, 'updated')),
        ];

        if ($baseUrl !== '' && $key !== '') {
            $rows['URL'] = rtrim($baseUrl, '/') . '/browse/' . $key;
        }

        return array_filter($rows, static fn (string $value) => $value !== '');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function parentLabel(array $fields): string
    {
        $parent = is_array($fields['parent'] ?? null) ? $fields['parent'] : [];

        if ($parent === []) {
            return '';
        }

        $parentKey = is_string($parent['key'] ?? null) ? $parent['key'] : '';
        $parentFields = is_array($parent['fields'] ?? null) ? $parent['fields'] : [];
        $parentSummary = self::stringField($parentFields, 'summary');

        return trim($parentKey . ($parentSummary !== '' ? " — {$parentSummary}" : ''));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function stringField(array $fields, string $name): string
    {
        return is_string($fields[$name] ?? null) ? $fields[$name] : '';
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function nestedField(array $fields, string $name, string $key): string
    {
        $value = is_array($fields[$name] ?? null) ? $fields[$name] : [];

        return is_string($value[$key] ?? null) ? $value[$key] : '';
    }

    /**
     * Jira returns ISO 8601 with milliseconds and an offset; a date and time is
     * all that is useful when skimming a ticket.
     */
    private static function date(string $raw): string
    {
        if ($raw === '') {
            return '';
        }

        $timestamp = strtotime($raw);

        return $timestamp === false ? $raw : date('Y-m-d H:i', $timestamp);
    }
}
