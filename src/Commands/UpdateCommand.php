<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\ConsoleHelper;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class UpdateCommand extends Command
{
    protected static string $defaultName = 'update';

    protected static string $defaultDescription = 'Update an existing Jira ticket';

    private JiraApiClient $jiraClient;

    private ConfigManager $config;

    private QuestionHelper $questionHelper;

    private ConsoleHelper $consoleHelper;

    protected function configure(): void
    {
        $this
            ->setName('update')
            ->setDescription('Update an existing Jira ticket')
            ->setHelp('This command allows you to update fields of an existing Jira ticket.')
            ->addArgument('issue-key', InputArgument::REQUIRED, 'The issue key to update (e.g. CAMPUS-395)')
            ->addOption('summary', 's', InputOption::VALUE_REQUIRED, 'New ticket summary/title')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'New ticket description')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'New issue type (e.g. Task, Story, Bug)')
            ->addOption('epic', null, InputOption::VALUE_REQUIRED, 'New epic or parent key')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'New parent key (alias for --epic)')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'New priority name (e.g. High, Medium, Low)')
            ->addOption('assignee', null, InputOption::VALUE_REQUIRED, 'New assignee (displayName, email, accountId, or "unassigned")')
            ->addOption('labels', 'l', InputOption::VALUE_REQUIRED, 'Comma-separated labels')
            ->addOption('sprint', null, InputOption::VALUE_REQUIRED, 'Sprint ID or "active"')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the payload JSON without updating the ticket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config = new ConfigManager();
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $this->questionHelper = $helper;
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

        $issueKey = strtoupper($input->getArgument('issue-key'));

        $this->consoleHelper->info("🔗 Fetching issue {$issueKey}...");
        $issue = $this->jiraClient->getIssue($issueKey);

        if (!$issue) {
            $this->consoleHelper->error("❌ Issue {$issueKey} not found or inaccessible.");

            return Command::FAILURE;
        }

        $projectKey = $issue['fields']['project']['key'] ?? '';
        if (!$projectKey) {
            $parts = explode('-', $issueKey);
            $projectKey = $parts[0] ?? '';
        }

        $isDryRun = (bool) $input->getOption('dry-run');
        $isNonInteractive = $input->getOption('no-interaction') || $this->hasUpdateOptions($input);

        try {
            if ($isNonInteractive) {
                $payload = $this->buildNonInteractivePayload($input, $projectKey);
            } else {
                $payload = $this->runInteractiveWizard($input, $output, $issue, $projectKey);
            }

            if (empty($payload['fields']) && empty($payload['sprint_id'])) {
                $this->consoleHelper->warning('No fields to update.');

                return Command::SUCCESS;
            }

            // Extract sprint ID if set
            $sprintId = $payload['sprint_id'] ?? null;
            unset($payload['sprint_id']);

            if ($isDryRun) {
                $this->consoleHelper->info('📋 Dry Run Payload:');
                $dryRunPayload = $payload;
                if ($sprintId) {
                    $dryRunPayload['_sprint_id'] = $sprintId;
                }
                $output->writeln(json_encode($dryRunPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return Command::SUCCESS;
            }

            if (!empty($payload['fields'])) {
                $this->consoleHelper->info("🚀 Updating issue {$issueKey}...");
                $this->jiraClient->updateIssue($issueKey, $payload);
                $this->consoleHelper->success('✅ Issue updated successfully!');
            }

            if ($sprintId) {
                $this->consoleHelper->info("🏃 Adding issue to sprint ID {$sprintId}...");
                if ($this->jiraClient->addIssueToSprint($issueKey, $sprintId)) {
                    $this->consoleHelper->success('✅ Added to sprint successfully!');
                } else {
                    $this->consoleHelper->warning('⚠️ Could not add to sprint.');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->consoleHelper->error('❌ Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function hasUpdateOptions(InputInterface $input): bool
    {
        return $input->getOption('summary') !== null
            || $input->getOption('description') !== null
            || $input->getOption('type') !== null
            || $input->getOption('epic') !== null
            || $input->getOption('parent') !== null
            || $input->getOption('priority') !== null
            || $input->getOption('assignee') !== null
            || $input->getOption('labels') !== null
            || $input->getOption('sprint') !== null;
    }

    private function buildNonInteractivePayload(InputInterface $input, string $projectKey): array
    {
        $payload = ['fields' => []];

        $summary = $input->getOption('summary');
        if ($summary !== null) {
            $payload['fields']['summary'] = $summary;
        }

        $description = $input->getOption('description');
        if ($description !== null) {
            $payload['fields']['description'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $description]],
                    ],
                ],
            ];
        }

        $type = $input->getOption('type');
        if ($type !== null) {
            $payload['fields']['issuetype'] = ['name' => $type];
        }

        $epicKey = $input->getOption('epic') ?? $input->getOption('parent');
        if ($epicKey !== null) {
            if (strtolower($epicKey) === 'none' || strtolower($epicKey) === 'null') {
                $payload['fields']['parent'] = null;
            } else {
                $payload['fields']['parent'] = ['key' => strtoupper($epicKey)];
            }
        }

        $priority = $input->getOption('priority');
        if ($priority !== null) {
            $payload['fields']['priority'] = ['name' => $priority];
        }

        $assignee = $input->getOption('assignee');
        if ($assignee !== null) {
            if (strtolower($assignee) === 'unassigned' || strtolower($assignee) === 'none') {
                $payload['fields']['assignee'] = null;
            } else {
                $accountId = $this->resolveAssigneeAccountId($assignee, $projectKey);
                $payload['fields']['assignee'] = ['accountId' => $accountId];
            }
        }

        $labelsRaw = $input->getOption('labels');
        if ($labelsRaw !== null) {
            $payload['fields']['labels'] = array_values(array_filter(array_map('trim', explode(',', $labelsRaw))));
        }

        $sprint = $input->getOption('sprint');
        if ($sprint !== null) {
            if (strtolower($sprint) === 'active') {
                $activeSprint = $this->jiraClient->getActiveSprint($projectKey);
                if ($activeSprint) {
                    $payload['sprint_id'] = (int) $activeSprint['id'];
                }
            } elseif (is_numeric($sprint)) {
                $payload['sprint_id'] = (int) $sprint;
            }
        }

        return $payload;
    }

    private function resolveAssigneeAccountId(string $assigneeInput, string $projectKey): string
    {
        $users = $this->jiraClient->getAssignableUsers($projectKey);
        foreach ($users as $user) {
            if ($user['accountId'] === $assigneeInput) {
                return $user['accountId'];
            }
            if (isset($user['displayName']) && strtolower($user['displayName']) === strtolower($assigneeInput)) {
                return $user['accountId'];
            }
            if (isset($user['emailAddress']) && strtolower($user['emailAddress']) === strtolower($assigneeInput)) {
                return $user['accountId'];
            }
        }

        // Try partial matching
        foreach ($users as $user) {
            if (isset($user['displayName']) && stripos($user['displayName'], $assigneeInput) !== false) {
                return $user['accountId'];
            }
        }

        throw new \Exception("Could not find assignable user matching '{$assigneeInput}'");
    }

    private function runInteractiveWizard(InputInterface $input, OutputInterface $output, array $issue, string $projectKey): array
    {
        $payload = ['fields' => []];

        $currentSummary = $issue['fields']['summary'] ?? '';
        $currentDesc = '';
        if (isset($issue['fields']['description']['content'][0]['content'][0]['text'])) {
            $currentDesc = $issue['fields']['description']['content'][0]['content'][0]['text'];
        }

        $this->consoleHelper->title('🔧 Jira Ticket Update Wizard');

        // 1. Summary
        $question = new Question("Summary [{$currentSummary}]: ", $currentSummary);
        $summary = $this->questionHelper->ask($input, $output, $question);
        if ($summary !== $currentSummary) {
            $payload['fields']['summary'] = $summary;
        }

        // 2. Description
        $question = new Question("Description [{$currentDesc}]: ", $currentDesc);
        $description = $this->questionHelper->ask($input, $output, $question);
        if ($description !== $currentDesc) {
            $payload['fields']['description'] = [
                'type' => 'doc',
                'version' => 1,
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => $description]],
                    ],
                ],
            ];
        }

        // 3. Issue Type
        $issueTypes = $this->jiraClient->getIssueTypes($projectKey);
        $currentTypeName = $issue['fields']['issuetype']['name'] ?? '';
        if (!empty($issueTypes)) {
            $typeChoices = [];
            $defaultIndex = 0;
            foreach ($issueTypes as $index => $type) {
                $typeChoices[$index] = $type['name'];
                if (strtolower($type['name']) === strtolower($currentTypeName)) {
                    $defaultIndex = $index;
                }
            }

            $question = new ChoiceQuestion("Select Issue Type [{$currentTypeName}]: ", $typeChoices, $defaultIndex);
            $selectedType = $this->questionHelper->ask($input, $output, $question);
            if ($selectedType !== $currentTypeName) {
                $payload['fields']['issuetype'] = ['name' => $selectedType];
            }
        }

        // 4. Priority
        $priorities = $this->jiraClient->getPriorities();
        $currentPriorityName = $issue['fields']['priority']['name'] ?? '';
        if (!empty($priorities)) {
            $priorityChoices = [];
            $defaultIndex = 0;
            foreach ($priorities as $index => $pri) {
                $priorityChoices[$index] = $pri['name'];
                if (strtolower($pri['name']) === strtolower($currentPriorityName)) {
                    $defaultIndex = $index;
                }
            }

            $question = new ChoiceQuestion("Select Priority [{$currentPriorityName}]: ", $priorityChoices, $defaultIndex);
            $selectedPriority = $this->questionHelper->ask($input, $output, $question);
            if ($selectedPriority !== $currentPriorityName) {
                $payload['fields']['priority'] = ['name' => $selectedPriority];
            }
        }

        // 5. Assignee
        $users = $this->jiraClient->getAssignableUsers($projectKey);
        $currentAssigneeName = $issue['fields']['assignee']['displayName'] ?? 'Unassigned';
        if (!empty($users)) {
            $userChoices = ['unassigned' => 'Unassigned'];
            $defaultIndex = 'unassigned';
            foreach ($users as $index => $u) {
                $displayName = $u['displayName'] ?? $u['emailAddress'] ?? $u['accountId'];
                $userChoices[$index] = $displayName;
                if (isset($issue['fields']['assignee']['accountId']) && $u['accountId'] === $issue['fields']['assignee']['accountId']) {
                    $defaultIndex = $index;
                }
            }

            $question = new ChoiceQuestion("Select Assignee [{$currentAssigneeName}]: ", $userChoices, $defaultIndex);
            $selectedUserKey = $this->questionHelper->ask($input, $output, $question);
            if ($selectedUserKey === 'unassigned') {
                if ($currentAssigneeName !== 'Unassigned') {
                    $payload['fields']['assignee'] = null;
                }
            } else {
                $selectedUser = $users[$selectedUserKey] ?? null;
                if ($selectedUser && ($selectedUser['displayName'] !== $currentAssigneeName)) {
                    $payload['fields']['assignee'] = ['accountId' => $selectedUser['accountId']];
                }
            }
        }

        // 6. Epic
        $epics = $this->jiraClient->getEpics($projectKey);
        $currentEpicKey = $issue['fields']['parent']['key'] ?? 'None';
        if (!empty($epics)) {
            $epicChoices = ['none' => 'None (no epic)'];
            $defaultIndex = 'none';
            foreach ($epics as $e) {
                $epicChoices[$e['key']] = "{$e['key']} - {$e['fields']['summary']}";
                if ($e['key'] === $currentEpicKey) {
                    $defaultIndex = $e['key'];
                }
            }

            $question = new ChoiceQuestion("Select Epic [{$currentEpicKey}]: ", $epicChoices, $defaultIndex);
            $selectedEpic = $this->questionHelper->ask($input, $output, $question);
            if ($selectedEpic === 'none') {
                if ($currentEpicKey !== 'None') {
                    $payload['fields']['parent'] = null;
                }
            } elseif ($selectedEpic !== $currentEpicKey) {
                $payload['fields']['parent'] = ['key' => $selectedEpic];
            }
        }

        // 7. Labels
        $currentLabels = isset($issue['fields']['labels']) ? implode(', ', $issue['fields']['labels']) : '';
        $question = new Question("Labels (comma separated) [{$currentLabels}]: ", $currentLabels);
        $labelsRaw = $this->questionHelper->ask($input, $output, $question);
        if ($labelsRaw !== $currentLabels) {
            $payload['fields']['labels'] = array_values(array_filter(array_map('trim', explode(',', $labelsRaw))));
        }

        // 8. Sprint
        $sprint = $this->jiraClient->getActiveSprint($projectKey);
        if ($sprint) {
            $question = new Question("Add to active sprint '{$sprint['name']}'? (y/N): ", 'n');
            $addToSprint = $this->questionHelper->ask($input, $output, $question);
            if (strtolower($addToSprint) === 'y') {
                $payload['sprint_id'] = (int) $sprint['id'];
            }
        }

        return $payload;
    }
}
