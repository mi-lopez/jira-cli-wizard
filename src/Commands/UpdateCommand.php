<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\AdfHelper;
use MiLopez\JiraCliWizard\Helpers\ConsoleHelper;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    protected static string $defaultName = 'update';

    protected static string $defaultDescription = 'Update an existing Jira ticket from the command line';

    private JiraApiClient $jiraClient;

    private ConfigManager $config;

    private ConsoleHelper $consoleHelper;

    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update an existing Jira ticket from the command line')
            ->setHelp('Partially updates an existing ticket. Only the fields you pass are changed.')
            ->addArgument('key', InputArgument::REQUIRED, 'Issue key to update (e.g. CAM-1755)')
            ->addOption('summary', 's', InputOption::VALUE_REQUIRED, 'New summary/title')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'New description (replaces existing)')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent issue or epic key (e.g. CAM-1075)')
            ->addOption('epic', null, InputOption::VALUE_REQUIRED, 'Epic key to link (alias for --parent)')
            ->addOption('labels', 'l', InputOption::VALUE_REQUIRED, 'Comma-separated labels (replaces existing)')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority name (e.g. High, Medium, Low)')
            ->addOption('add-to-sprint', null, InputOption::VALUE_REQUIRED, 'Sprint ID or "active" to add the issue to')
            ->addOption('comment', null, InputOption::VALUE_REQUIRED, 'Add a comment to the issue')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be sent without applying changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config = new ConfigManager();
        $this->consoleHelper = new ConsoleHelper($output);

        if (!$this->config->isConfigured()) {
            $this->consoleHelper->error('Jira CLI is not configured. Please run: jira-wizard configure');

            return Command::FAILURE;
        }

        $jiraUrl = $this->config->get('jira_url');
        $jiraEmail = $this->config->get('jira_email');
        $jiraToken = $this->config->get('jira_token');

        if (!$jiraUrl || !$jiraEmail || !$jiraToken) {
            $this->consoleHelper->error('❌ Missing configuration values');

            return Command::FAILURE;
        }

        $this->jiraClient = new JiraApiClient($jiraUrl, $jiraEmail, $jiraToken);

        $issueKey = strtoupper((string) $input->getArgument('key'));
        $isDryRun = (bool) $input->getOption('dry-run');

        $fields = $this->buildFields($input);
        $sprintId = $this->resolveSprintId($input, $issueKey, $isDryRun);
        $comment = $input->getOption('comment');

        if ($fields === [] && $sprintId === null && $comment === null) {
            $this->consoleHelper->error('Nothing to update. Provide at least one of --summary, --description, --priority, --parent/--epic, --labels, --add-to-sprint or --comment.');

            return Command::FAILURE;
        }

        if ($isDryRun) {
            $preview = ['key' => $issueKey, 'fields' => (object) $fields];
            if ($sprintId !== null) {
                $preview['_sprint_id'] = $sprintId;
            }
            if ($comment !== null) {
                $preview['_comment'] = (string) $comment;
            }
            $output->writeln(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        if (!$this->jiraClient->testConnection()) {
            $this->consoleHelper->error('❌ Failed to connect to Jira. Please check your configuration.');

            return Command::FAILURE;
        }

        try {
            if ($fields !== []) {
                $this->jiraClient->updateIssue($issueKey, $fields);
                $this->consoleHelper->success("✅ Updated {$issueKey}");
            }

            if ($sprintId !== null) {
                if ($this->jiraClient->addIssueToSprint($issueKey, $sprintId)) {
                    $this->consoleHelper->success("✅ Added {$issueKey} to sprint {$sprintId}");
                } else {
                    $this->consoleHelper->warning('⚠️  Could not add to sprint');
                }
            }

            if ($comment !== null) {
                if ($this->jiraClient->addComment($issueKey, AdfHelper::descriptionToDoc((string) $comment))) {
                    $this->consoleHelper->success('✅ Comment added');
                } else {
                    $this->consoleHelper->warning('⚠️  Could not add comment');
                }
            }

            $output->writeln($issueKey);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->consoleHelper->error('❌ Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Build the partial fields payload from the provided options only.
     */
    public function buildFields(InputInterface $input): array
    {
        $fields = [];

        $summary = $input->getOption('summary');
        if ($summary !== null) {
            $fields['summary'] = $summary;
        }

        $description = $input->getOption('description');
        if ($description !== null) {
            $fields['description'] = AdfHelper::descriptionToDoc((string) $description);
        }

        $epicKey = $input->getOption('epic') ?? $input->getOption('parent');
        if ($epicKey !== null) {
            $fields['parent'] = ['key' => strtoupper($epicKey)];
        }

        $priority = $input->getOption('priority');
        if ($priority !== null) {
            $fields['priority'] = ['name' => $priority];
        }

        $labelsRaw = $input->getOption('labels');
        if ($labelsRaw !== null) {
            $fields['labels'] = $this->parseLabels((string) $labelsRaw);
        }

        return $fields;
    }

    private function resolveSprintId(InputInterface $input, string $issueKey, bool $isDryRun): ?int
    {
        $sprint = $input->getOption('add-to-sprint');

        if ($sprint === null) {
            return null;
        }

        if (strtolower($sprint) === 'active') {
            if ($isDryRun) {
                return null;
            }
            $projectKey = explode('-', $issueKey)[0];
            $activeSprint = $this->jiraClient->getActiveSprint($projectKey);

            return $activeSprint ? (int) $activeSprint['id'] : null;
        }

        return (int) $sprint;
    }

    private function parseLabels(string $labelsRaw): array
    {
        $labels = array_map('trim', explode(',', $labelsRaw));
        $labels = array_filter($labels, static fn (string $label): bool => $label !== '');

        return array_values(array_unique($labels));
    }
}
