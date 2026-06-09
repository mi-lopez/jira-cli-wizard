<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\ConsoleHelper;
use MiLopez\JiraCliWizard\Helpers\MarkdownToAdf;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class CreateTicketCommand extends Command
{
    protected static string $defaultName = 'create';

    protected static string $defaultDescription = 'Create a new Jira ticket using the interactive wizard';

    private JiraApiClient $jiraClient;

    private ConfigManager $config;

    private QuestionHelper $questionHelper;

    private ConsoleHelper $consoleHelper;

    protected function configure(): void
    {
        $this
            ->setName('create')
            ->setDescription('Create a new Jira ticket using the interactive wizard')
            ->setHelp('This command guides you through creating a Jira ticket with smart defaults and suggestions.')
            ->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project key (e.g. ALDO)')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Issue type (e.g. Task, Story, Epic)')
            ->addOption('summary', 's', InputOption::VALUE_REQUIRED, 'Ticket summary/title')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Ticket description', '')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent issue or epic key (e.g. ALDO-10)')
            ->addOption('epic', null, InputOption::VALUE_REQUIRED, 'Epic key to link (alias for --parent)')
            ->addOption('labels', 'l', InputOption::VALUE_REQUIRED, 'Comma-separated labels (e.g. backend,upgrade)')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority name (e.g. High, Medium, Low)')
            ->addOption('sprint', null, InputOption::VALUE_REQUIRED, 'Sprint ID or "active" to use the current active sprint')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the payload JSON without creating the ticket');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config = new ConfigManager();
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $this->questionHelper = $helper;
        $this->consoleHelper = new ConsoleHelper($output);

        // Check if configured
        if (!$this->config->isConfigured()) {
            $this->consoleHelper->error('Jira CLI is not configured. Please run: jira-wizard configure');

            return Command::FAILURE;
        }

        // Initialize Jira client
        $jiraUrl = $this->config->get('jira_url');
        $jiraEmail = $this->config->get('jira_email');
        $jiraToken = $this->config->get('jira_token');

        if (!$jiraUrl || !$jiraEmail || !$jiraToken) {
            $this->consoleHelper->error('❌ Missing configuration values');

            return Command::FAILURE;
        }

        $this->jiraClient = new JiraApiClient($jiraUrl, $jiraEmail, $jiraToken);

        $isDryRun = (bool) $input->getOption('dry-run');
        $isNonInteractive = $input->getOption('no-interaction') || $this->hasRequiredFlags($input);

        if ($isNonInteractive) {
            return $this->executeNonInteractive($input, $output, $isDryRun);
        }

        // Test connection only for interactive mode (non-interactive tests inline)
        $this->consoleHelper->info('🔗 Testing Jira connection...');
        if (!$this->jiraClient->testConnection()) {
            $this->consoleHelper->error('❌ Failed to connect to Jira. Please check your configuration.');

            return Command::FAILURE;
        }

        $this->consoleHelper->success('✅ Connected to Jira successfully!');
        $this->consoleHelper->separator();

        try {
            // Start the wizard
            $this->consoleHelper->title('🎯 Jira Ticket Creation Wizard');

            $ticketData = $this->runWizard($input, $output);

            if (!$ticketData) {
                $this->consoleHelper->warning('Ticket creation cancelled.');

                return Command::SUCCESS;
            }

            // Create the ticket
            $this->consoleHelper->info('🚀 Creating ticket...');
            $result = $this->jiraClient->createIssue($ticketData);

            $issueKey = $result['key'];
            $issueUrl = $this->config->get('jira_url') . '/browse/' . $issueKey;

            $this->consoleHelper->success('✅ Ticket created successfully!');
            $this->consoleHelper->info("📝 Issue Key: {$issueKey}");
            $this->consoleHelper->info("🔗 URL: {$issueUrl}");

            // Add to sprint if selected
            if (isset($ticketData['sprint_id'])) {
                $this->consoleHelper->info('📋 Adding to sprint...');
                if ($this->jiraClient->addIssueToSprint($issueKey, $ticketData['sprint_id'])) {
                    $this->consoleHelper->success('✅ Added to sprint!');
                } else {
                    $this->consoleHelper->warning('⚠️  Could not add to sprint (ticket created successfully)');
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->consoleHelper->error('❌ Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function hasRequiredFlags(InputInterface $input): bool
    {
        return $input->getOption('project') !== null
            && $input->getOption('type') !== null
            && $input->getOption('summary') !== null;
    }

    private function executeNonInteractive(InputInterface $input, OutputInterface $output, bool $isDryRun): int
    {
        $projectKey = $input->getOption('project');
        $typeName = $input->getOption('type');
        $summary = $input->getOption('summary');

        if (!$projectKey || !$typeName || !$summary) {
            $output->writeln('<error>Non-interactive mode requires --project, --type, and --summary.</error>');

            return Command::FAILURE;
        }

        try {
            $sprintId = $this->resolveSprintId($input, $projectKey);
            $payload = $this->buildNonInteractivePayload($input, $projectKey, $typeName, $summary);

            if ($isDryRun) {
                $dryRunPayload = $payload;
                if ($sprintId !== null) {
                    $dryRunPayload['_sprint_id'] = $sprintId;
                }
                $output->writeln(json_encode($dryRunPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return Command::SUCCESS;
            }

            if (!$this->jiraClient->testConnection()) {
                $output->writeln('<error>Failed to connect to Jira. Please check your configuration.</error>');

                return Command::FAILURE;
            }

            $result = $this->jiraClient->createIssue($payload);
            $issueKey = $result['key'];

            if ($sprintId !== null) {
                $this->jiraClient->addIssueToSprint($issueKey, $sprintId);
            }

            $output->write($issueKey);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    private function resolveSprintId(InputInterface $input, string $projectKey): ?int
    {
        $sprint = $input->getOption('sprint');

        if ($sprint === null) {
            return null;
        }

        if (strtolower($sprint) === 'active') {
            $activeSprint = $this->jiraClient->getActiveSprint($projectKey);

            return $activeSprint ? (int) $activeSprint['id'] : null;
        }

        return (int) $sprint;
    }

    public function buildNonInteractivePayload(InputInterface $input, string $projectKey, string $typeName, string $summary): array
    {
        $description = (string) $input->getOption('description');

        $payload = [
            'fields' => [
                'project' => ['key' => strtoupper($projectKey)],
                'issuetype' => ['name' => $typeName],
                'summary' => $summary,
            ],
        ];

        if ($description !== '') {
            $payload['fields']['description'] = MarkdownToAdf::convert($description);
        }

        $epicKey = $input->getOption('epic') ?? $input->getOption('parent');
        if ($epicKey !== null) {
            $payload['fields']['parent'] = ['key' => strtoupper($epicKey)];
        }

        $priority = $input->getOption('priority');
        if ($priority !== null) {
            $payload['fields']['priority'] = ['name' => $priority];
        }

        $labelsRaw = $input->getOption('labels');
        if ($labelsRaw !== null && $labelsRaw !== '') {
            $payload['fields']['labels'] = array_values(array_filter(array_map('trim', explode(',', $labelsRaw))));
        }

        return $payload;
    }

    private function runWizard(InputInterface $input, OutputInterface $output): ?array
    {
        // Step 1: Select Project
        $this->consoleHelper->step('1/7', 'Select Project');
        $project = $this->selectProject($input, $output);
        if (!$project) {
            return null;
        }

        // Step 2: Select Issue Type
        $this->consoleHelper->step('2/7', 'Select Issue Type');
        $issueType = $this->selectIssueType($input, $output, $project['key']);
        if (!$issueType) {
            return null;
        }

        // Step 3: Enter Summary
        $this->consoleHelper->step('3/7', 'Enter Summary');
        $summary = $this->enterSummary($input, $output);
        if (!$summary) {
            return null;
        }

        // Step 4: Enter Description
        $this->consoleHelper->step('4/7', 'Enter Description');
        $description = $this->enterDescription($input, $output);

        // Step 5: Select Priority
        $this->consoleHelper->step('5/7', 'Select Priority');
        $priority = $this->selectPriority($input, $output);

        // Step 6: Select Assignee
        $this->consoleHelper->step('6/7', 'Select Assignee');
        $assignee = $this->selectAssignee($input, $output, $project['key']);

        // Step 7: Additional Options
        $this->consoleHelper->step('7/7', 'Additional Options');
        $additionalOptions = $this->selectAdditionalOptions($input, $output, $project['key']);

        // Build issue data
        $issueData = [
            'fields' => [
                'project' => ['key' => $project['key']],
                'issuetype' => ['id' => $issueType['id']],
                'summary' => $summary,
                'description' => MarkdownToAdf::convert($description),
            ],
        ];

        // Add optional fields
        if ($priority) {
            $issueData['fields']['priority'] = ['id' => $priority['id']];
        }

        if ($assignee) {
            $issueData['fields']['assignee'] = ['accountId' => $assignee['accountId']];
        }

        if (isset($additionalOptions['epic'])) {
            $issueData['fields']['parent'] = ['key' => $additionalOptions['epic']['key']];
        }

        // Store sprint ID separately for later processing
        if (isset($additionalOptions['sprint'])) {
            $issueData['sprint_id'] = $additionalOptions['sprint']['id'];
        }

        // Show summary
        $this->showSummary($output, $project, $issueType, $summary, $description, $priority, $assignee, $additionalOptions);

        // Confirm creation
        $confirmQuestion = new Question('🤔 Create this ticket? (Y/n): ', 'y');
        $confirm = $this->questionHelper->ask($input, $output, $confirmQuestion);

        if (strtolower($confirm) !== 'y') {
            return null;
        }

        return $issueData;
    }

    private function selectProject(InputInterface $input, OutputInterface $output): ?array
    {
        $projects = $this->jiraClient->getProjects();

        if (empty($projects)) {
            $this->consoleHelper->error('No projects found or no access to projects.');

            return null;
        }

        $this->consoleHelper->info('Available projects:');
        $choices = [];
        $projectMap = [];

        foreach ($projects as $index => $project) {
            $displayName = "{$project['key']} - {$project['name']}";
            $choices[$index] = $displayName;
            $projectMap[$index] = $project;
            $output->writeln("  [{$index}] {$displayName}");
        }

        $question = new ChoiceQuestion(
            'Select a project (enter number or project key):',
            $choices
        );

        // Allow both number and project key
        $question->setValidator(function ($answer) use ($projects, $projectMap, $output) {
            // If it's a number, use it directly
            if (is_numeric($answer) && isset($projectMap[$answer])) {
                return $answer;
            }

            // If it's a project key, find the index (case insensitive)
            foreach ($projects as $index => $project) {
                if (strtoupper($project['key']) === strtoupper($answer)) {
                    return $index;
                }
            }

            // Try partial matching for project key
            $partialMatches = [];
            foreach ($projects as $index => $project) {
                if (stripos($project['key'], $answer) !== false) {
                    $partialMatches[] = $index;
                }
            }

            // Try partial matching for project name
            if (empty($partialMatches)) {
                foreach ($projects as $index => $project) {
                    if (stripos($project['name'], $answer) !== false) {
                        $partialMatches[] = $index;
                    }
                }
            }

            if (count($partialMatches) === 1) {
                return $partialMatches[0];
            } elseif (count($partialMatches) > 1) {
                $output->writeln("\n<comment>Multiple matches found for '{$answer}':</comment>");
                foreach ($partialMatches as $index) {
                    $project = $projects[$index];
                    $output->writeln("  [{$index}] {$project['key']} - {$project['name']}");
                }
                throw new \InvalidArgumentException('Multiple matches found. Please be more specific.');
            }

            throw new \InvalidArgumentException("No project found matching '{$answer}'. Please enter a number, project key, or part of the project name.");
        });

        $selectedIndex = $this->questionHelper->ask($input, $output, $question);

        return $projectMap[$selectedIndex] ?? null;
    }

    private function selectIssueType(InputInterface $input, OutputInterface $output, string $projectKey): ?array
    {
        $issueTypes = $this->jiraClient->getIssueTypes($projectKey);

        if (empty($issueTypes)) {
            $this->consoleHelper->error('No issue types found for this project.');

            return null;
        }

        $this->consoleHelper->info('Available issue types:');
        $choices = [];
        $issueTypeMap = [];

        foreach ($issueTypes as $index => $type) {
            if (!$type['subtask']) { // Only show non-subtask types
                $displayName = "{$type['name']} - {$type['description']}";
                $choices[$index] = $displayName;
                $issueTypeMap[$index] = $type;
                $output->writeln("  [{$index}] {$displayName}");
            }
        }

        $question = new ChoiceQuestion(
            'Select issue type (enter number or type name):',
            $choices
        );

        $question->setValidator(function ($answer) use ($issueTypes, $issueTypeMap, $output) {
            // If it's a number, use it directly
            if (is_numeric($answer) && isset($issueTypeMap[$answer])) {
                return $answer;
            }

            // If it's an issue type name, find the index (case insensitive)
            foreach ($issueTypes as $index => $type) {
                if (!$type['subtask'] && strtolower($type['name']) === strtolower($answer)) {
                    return $index;
                }
            }

            // Try partial matching for issue type name
            $partialMatches = [];
            foreach ($issueTypes as $index => $type) {
                if (!$type['subtask'] && stripos($type['name'], $answer) !== false) {
                    $partialMatches[] = $index;
                }
            }

            if (count($partialMatches) === 1) {
                return $partialMatches[0];
            } elseif (count($partialMatches) > 1) {
                $output->writeln("\n<comment>Multiple matches found for '{$answer}':</comment>");
                foreach ($partialMatches as $index) {
                    $type = $issueTypes[$index];
                    $output->writeln("  [{$index}] {$type['name']} - {$type['description']}");
                }
                throw new \InvalidArgumentException('Multiple matches found. Please be more specific.');
            }

            throw new \InvalidArgumentException("No issue type found matching '{$answer}'. Please enter a number or issue type name.");
        });

        $selectedIndex = $this->questionHelper->ask($input, $output, $question);

        return $issueTypeMap[$selectedIndex] ?? null;
    }

    private function enterSummary(InputInterface $input, OutputInterface $output): ?string
    {
        $question = new Question('Enter ticket summary: ');
        $question->setValidator(function ($value) {
            if (empty(trim($value))) {
                throw new \Exception('Summary cannot be empty');
            }
            if (strlen($value) > 255) {
                throw new \Exception('Summary must be less than 255 characters');
            }

            return $value;
        });

        return $this->questionHelper->ask($input, $output, $question);
    }

    private function enterDescription(InputInterface $input, OutputInterface $output): string
    {
        $question = new Question('Enter description (optional): ', '');

        return $this->questionHelper->ask($input, $output, $question) ?? '';
    }

    private function selectPriority(InputInterface $input, OutputInterface $output): ?array
    {
        $priorities = $this->jiraClient->getPriorities();

        if (empty($priorities)) {
            return null;
        }

        $this->consoleHelper->info('Available priorities:');
        $choices = ['skip' => 'Skip (use default)'];
        $priorityMap = ['skip' => null];

        $output->writeln('  [skip] Skip (use default)');

        foreach ($priorities as $index => $priority) {
            $displayName = $priority['name'];
            $choices[$index] = $displayName;
            $priorityMap[$index] = $priority;
            $output->writeln("  [{$index}] {$displayName}");
        }

        $question = new ChoiceQuestion(
            'Select priority (enter number, priority name, or "skip"):',
            $choices,
            'skip'
        );

        $question->setValidator(function ($answer) use ($priorities, $priorityMap, $output) {
            // Handle skip
            if (strtolower($answer) === 'skip') {
                return 'skip';
            }

            // If it's a number, use it directly
            if (is_numeric($answer) && isset($priorityMap[$answer])) {
                return $answer;
            }

            // If it's a priority name, find the index (case insensitive)
            foreach ($priorities as $index => $priority) {
                if (strtolower($priority['name']) === strtolower($answer)) {
                    return $index;
                }
            }

            // Try partial matching
            $partialMatches = [];
            foreach ($priorities as $index => $priority) {
                if (stripos($priority['name'], $answer) !== false) {
                    $partialMatches[] = $index;
                }
            }

            if (count($partialMatches) === 1) {
                return $partialMatches[0];
            } elseif (count($partialMatches) > 1) {
                $output->writeln("\n<comment>Multiple matches found for '{$answer}':</comment>");
                foreach ($partialMatches as $index) {
                    $priority = $priorities[$index];
                    $output->writeln("  [{$index}] {$priority['name']}");
                }
                throw new \InvalidArgumentException('Multiple matches found. Please be more specific.');
            }

            throw new \InvalidArgumentException("No priority found matching '{$answer}'. Please enter a number, priority name, or 'skip'.");
        });

        $selectedIndex = $this->questionHelper->ask($input, $output, $question);

        if ($selectedIndex === 'skip') {
            return null;
        }

        return $priorityMap[$selectedIndex] ?? null;
    }

    private function selectAssignee(InputInterface $input, OutputInterface $output, string $projectKey): ?array
    {
        $users = $this->jiraClient->getAssignableUsers($projectKey);

        if (empty($users)) {
            $this->consoleHelper->warning('No assignable users found.');

            return null;
        }

        $this->consoleHelper->info('Available assignees:');
        $choices = ['unassigned' => 'Unassigned'];
        $userMap = ['unassigned' => null];

        $output->writeln('  [unassigned] Unassigned');

        foreach ($users as $index => $user) {
            $displayName = $user['displayName'] ?? $user['emailAddress'] ?? $user['accountId'];
            $choices[$index] = $displayName;
            $userMap[$index] = $user;
            $output->writeln("  [{$index}] {$displayName}");
        }

        $question = new ChoiceQuestion(
            'Select assignee (enter number, name, or "unassigned"):',
            $choices,
            'unassigned'
        );

        $question->setValidator(function ($answer) use ($users, $userMap, $output) {
            // Handle unassigned
            if (strtolower($answer) === 'unassigned') {
                return 'unassigned';
            }

            // If it's a number, use it directly
            if (is_numeric($answer) && isset($userMap[$answer])) {
                return $answer;
            }

            // Try to find by display name or email (case insensitive)
            foreach ($users as $index => $user) {
                $displayName = $user['displayName'] ?? '';
                $email = $user['emailAddress'] ?? '';

                if (strtolower($displayName) === strtolower($answer) ||
                    strtolower($email) === strtolower($answer)) {
                    return $index;
                }
            }

            // Try partial matching
            $partialMatches = [];
            foreach ($users as $index => $user) {
                $displayName = $user['displayName'] ?? '';
                $email = $user['emailAddress'] ?? '';

                if (stripos($displayName, $answer) !== false ||
                    stripos($email, $answer) !== false) {
                    $partialMatches[] = $index;
                }
            }

            if (count($partialMatches) === 1) {
                return $partialMatches[0];
            } elseif (count($partialMatches) > 1) {
                $output->writeln("\n<comment>Multiple matches found for '{$answer}':</comment>");
                foreach ($partialMatches as $index) {
                    $user = $users[$index];
                    $displayName = $user['displayName'] ?? $user['emailAddress'] ?? $user['accountId'];
                    $output->writeln("  [{$index}] {$displayName}");
                }
                throw new \InvalidArgumentException('Multiple matches found. Please be more specific.');
            }

            throw new \InvalidArgumentException("No assignee found matching '{$answer}'. Please enter a number, name, or 'unassigned'.");
        });

        $selectedIndex = $this->questionHelper->ask($input, $output, $question);

        if ($selectedIndex === 'unassigned') {
            return null;
        }

        return $userMap[$selectedIndex] ?? null;
    }

    private function selectAdditionalOptions(InputInterface $input, OutputInterface $output, string $projectKey): array
    {
        $options = [];

        // Sprint selection
        $this->consoleHelper->info('🏃 Sprint Options');
        $sprint = $this->jiraClient->getActiveSprint($projectKey);
        if ($sprint) {
            $question = new Question("Add to active sprint '{$sprint['name']}'? (y/N): ", 'n');
            $addToSprint = $this->questionHelper->ask($input, $output, $question);

            if (strtolower($addToSprint) === 'y') {
                $options['sprint'] = $sprint;
                $this->consoleHelper->success("✅ Will add to sprint: {$sprint['name']}");
            }
        } else {
            $this->consoleHelper->info('No active sprint found for this project.');
        }

        // Epic selection
        $this->consoleHelper->info('📚 Epic Options');
        $epics = $this->jiraClient->getEpics($projectKey);
        if (!empty($epics)) {
            $epicChoices = ['skip' => 'Skip (no epic)'];
            foreach ($epics as $epic) {
                $epicChoices[$epic['key']] = "{$epic['key']} - {$epic['fields']['summary']}";
            }

            $question = new ChoiceQuestion('Select epic (optional):', $epicChoices, 'skip');
            $selectedEpicKey = $this->questionHelper->ask($input, $output, $question);

            if ($selectedEpicKey !== 'skip') {
                $options['epic'] = array_filter($epics, fn ($e) => $e['key'] === $selectedEpicKey)[0] ?? null;
                if ($options['epic']) {
                    $this->consoleHelper->success("✅ Will link to epic: {$options['epic']['key']}");
                }
            }
        } else {
            $this->consoleHelper->info('No epics found for this project.');
        }

        return $options;
    }

    private function showSummary(OutputInterface $output, array $project, array $issueType, string $summary, string $description, ?array $priority, ?array $assignee, array $additionalOptions): void
    {
        $this->consoleHelper->separator();
        $this->consoleHelper->title('📋 Ticket Summary');

        $output->writeln("📁 <info>Project:</info> {$project['key']} - {$project['name']}");
        $output->writeln("🎯 <info>Type:</info> {$issueType['name']}");
        $output->writeln("📝 <info>Summary:</info> {$summary}");

        if ($description) {
            $output->writeln('📄 <info>Description:</info> ' . substr($description, 0, 100) . (strlen($description) > 100 ? '...' : ''));
        }

        if ($priority) {
            $output->writeln("⚡ <info>Priority:</info> {$priority['name']}");
        }

        if ($assignee) {
            $output->writeln("👤 <info>Assignee:</info> {$assignee['displayName']}");
        }

        if (isset($additionalOptions['sprint'])) {
            $output->writeln("🏃 <info>Sprint:</info> {$additionalOptions['sprint']['name']}");
        }

        if (isset($additionalOptions['epic'])) {
            $output->writeln("📚 <info>Epic:</info> {$additionalOptions['epic']['key']}");
        }

        $this->consoleHelper->separator();
    }
}
