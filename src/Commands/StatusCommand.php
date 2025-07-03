<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\ConsoleHelper;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    protected static string $defaultName = 'status';

    protected static string $defaultDescription = 'Show current configuration and connection status';

    private ConfigManager $config;

    private ConsoleHelper $consoleHelper;

    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Show current configuration and connection status')
            ->setHelp('This command displays your current configuration and tests the connection to Jira.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config = new ConfigManager();
        $this->consoleHelper = new ConsoleHelper($output);

        $this->consoleHelper->title('📊 Jira CLI Status');

        // Check if configured
        if (!$this->config->isConfigured()) {
            $this->consoleHelper->error('❌ Jira CLI is not configured');
            $this->consoleHelper->info('Run: jira-wizard configure');

            return Command::FAILURE;
        }

        // Show configuration
        $this->showConfiguration($output);

        // Test connection
        $this->testConnection($output);

        // Show usage tips
        $this->showUsageTips($output);

        return Command::SUCCESS;
    }

    private function showConfiguration(OutputInterface $output): void
    {
        $this->consoleHelper->info('📋 Current Configuration');
        $this->consoleHelper->separator();

        $jiraUrl = $this->config->get('jira_url');
        $jiraEmail = $this->config->get('jira_email');
        /** @var string $jiraToken */
        $jiraToken = $this->config->get('jira_token');

        $configItems = [
            "🌐 Jira URL: {$jiraUrl}",
            "📧 Email: {$jiraEmail}",
            '🔑 API Token: ' . $this->maskToken($jiraToken),
            "📁 Config File: {$this->config->getConfigFile()}",
        ];

        $this->consoleHelper->box($configItems, 'cyan');
        $output->writeln('');
    }

    private function testConnection(OutputInterface $output): void
    {
        $this->consoleHelper->info('🔗 Testing Connection');
        $this->consoleHelper->separator();

        try {
            $jiraUrl = $this->config->get('jira_url');
            $jiraEmail = $this->config->get('jira_email');
            $jiraToken = $this->config->get('jira_token');

            if (!$jiraUrl || !$jiraEmail || !$jiraToken) {
                $this->consoleHelper->error('❌ Missing configuration values');
                $this->consoleHelper->warning('Please check your credentials with: jira-wizard configure');

                return;
            }

            $client = new JiraApiClient($jiraUrl, $jiraEmail, $jiraToken);

            // Test basic connection
            if (!$client->testConnection()) {
                $this->consoleHelper->error('❌ Connection failed');
                $this->consoleHelper->warning('Please check your credentials with: jira-wizard configure');

                return;
            }

            $this->consoleHelper->success('✅ Connection successful!');

            // Get user information
            $user = $client->getCurrentUser();
            $output->writeln("👋 <info>Logged in as:</info> {$user['displayName']} ({$user['emailAddress']})");

            // Get accessible projects count
            $projects = $client->getProjects();
            $projectCount = count($projects);
            $output->writeln("📁 <info>Accessible projects:</info> {$projectCount}");

            if ($projectCount > 0) {
                $output->writeln('   <comment>Recent projects:</comment>');
                $recentProjects = array_slice($projects, 0, 3);
                foreach ($recentProjects as $project) {
                    $output->writeln("   • {$project['key']} - {$project['name']}");
                }

                if ($projectCount > 3) {
                    $remaining = $projectCount - 3;
                    $output->writeln("   <comment>... and {$remaining} more</comment>");
                }
            }
        } catch (\Exception $e) {
            $this->consoleHelper->error('❌ Connection failed: ' . $e->getMessage());
            $this->consoleHelper->warning('Please reconfigure with: jira-wizard configure');
        }

        $output->writeln('');
    }

    private function showUsageTips(OutputInterface $output): void
    {
        $this->consoleHelper->info('💡 Usage Tips');
        $this->consoleHelper->separator();

        $tips = [
            '🎯 Create a ticket: jira-wizard create',
            '🔧 Reconfigure: jira-wizard configure',
            '📊 Check status: jira-wizard status',
            '❓ Get help: jira-wizard --help',
        ];

        foreach ($tips as $tip) {
            $output->writeln("  {$tip}");
        }

        $output->writeln('');
        $this->consoleHelper->info('🚀 Pro tip: Use branch names like "feature/PROJ-123-description" for automatic issue linking!');
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 4) . substr($token, -4);
    }
}
