<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'list', description: 'List Jira resources as JSON (for scripting/AI agents)')]
class ListCommand extends Command
{
    public const RESOURCES = ['projects', 'issue-types', 'priorities', 'epics', 'sprints', 'transitions'];

    protected function configure(): void
    {
        $resourceList = implode(', ', self::RESOURCES);

        $this
            ->setName('list')
            ->setDescription('List Jira resources as JSON (for scripting/AI agents)')
            ->setHelp(
                "Available resources: {$resourceList}\n\n"
                . "Examples:\n"
                . "  jira-wizard list projects\n"
                . "  jira-wizard list issue-types --project=ALDO\n"
                . "  jira-wizard list priorities\n"
                . "  jira-wizard list epics --project=ALDO\n"
                . "  jira-wizard list transitions --issue=ALDO-123\n"
            )
            ->addArgument(
                'resource',
                InputArgument::OPTIONAL,
                "Resource to list. One of: {$resourceList}"
            )
            ->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project key (required for issue-types and epics)')
            ->addOption('issue', 'i', InputOption::VALUE_REQUIRED, 'Issue key (required for transitions)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resource = $input->getArgument('resource');

        if (!$resource) {
            $resourceList = implode(', ', self::RESOURCES);
            $output->writeln("<info>Available resources:</info> {$resourceList}");
            $output->writeln('');
            $output->writeln('Usage: jira-wizard list <resource> [--project=KEY] [--issue=KEY]');

            return Command::SUCCESS;
        }

        if (!in_array($resource, self::RESOURCES, true)) {
            $resourceList = implode(', ', self::RESOURCES);
            $output->writeln("<error>Unknown resource '{$resource}'. Available: {$resourceList}</error>");

            return Command::FAILURE;
        }

        $config = new ConfigManager();

        if (!$config->isConfigured()) {
            $output->writeln('<error>Jira CLI is not configured. Please run: jira-wizard configure</error>');

            return Command::FAILURE;
        }

        $jiraUrl = $config->get('jira_url');
        $jiraEmail = $config->get('jira_email');
        $jiraToken = $config->get('jira_token');

        if (!$jiraUrl || !$jiraEmail || !$jiraToken) {
            $output->writeln('<error>Missing configuration values.</error>');

            return Command::FAILURE;
        }

        $client = new JiraApiClient($jiraUrl, $jiraEmail, $jiraToken);

        try {
            $data = $this->fetchResource($client, $resource, $input);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }

    private function fetchResource(JiraApiClient $client, string $resource, InputInterface $input): array
    {
        switch ($resource) {
            case 'projects':
                return array_map(
                    static fn (array $p) => ['key' => $p['key'], 'name' => $p['name']],
                    $client->getProjects()
                );

            case 'issue-types':
                $projectKey = $input->getOption('project');

                if (!$projectKey) {
                    throw new \InvalidArgumentException('--project is required for issue-types');
                }

                return array_values(array_map(
                    static fn (array $t) => ['id' => $t['id'], 'name' => $t['name'], 'subtask' => $t['subtask']],
                    array_filter($client->getIssueTypes($projectKey), static fn (array $t) => !$t['subtask'])
                ));

            case 'priorities':
                return array_map(
                    static fn (array $p) => ['id' => $p['id'], 'name' => $p['name']],
                    $client->getPriorities()
                );

            case 'epics':
                $projectKey = $input->getOption('project');

                if (!$projectKey) {
                    throw new \InvalidArgumentException('--project is required for epics');
                }

                return array_map(
                    static fn (array $e) => ['key' => $e['key'], 'summary' => $e['fields']['summary']],
                    $client->getEpics($projectKey)
                );

            case 'transitions':
                // Keyed off the issue, not the project: which moves are legal
                // depends on where this ticket currently stands in the workflow.
                $issueKey = $input->getOption('issue');

                if (!$issueKey) {
                    throw new \InvalidArgumentException('--issue is required for transitions');
                }

                return array_map(
                    static fn (array $t) => [
                        'id' => $t['id'],
                        'name' => $t['name'],
                        'to' => $t['to']['name'] ?? null,
                    ],
                    $client->getTransitions(strtoupper($issueKey))
                );

            case 'sprints':
                $projectKey = $input->getOption('project');

                if (!$projectKey) {
                    throw new \InvalidArgumentException('--project is required for sprints');
                }

                $activeSprint = $client->getActiveSprint($projectKey);

                return $activeSprint
                    ? [['id' => $activeSprint['id'], 'name' => $activeSprint['name'], 'state' => $activeSprint['state']]]
                    : [];

            default:
                throw new \InvalidArgumentException("Unknown resource: {$resource}");
        }
    }
}
