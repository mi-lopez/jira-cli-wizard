<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Commands;

use MiLopez\JiraCliWizard\ConfigManager;
use MiLopez\JiraCliWizard\Helpers\ConsoleHelper;
use MiLopez\JiraCliWizard\JiraApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class ConfigureCommand extends Command
{
    protected static string $defaultName = 'configure';

    protected static string $defaultDescription = 'Configure Jira CLI with your credentials and settings';

    private ConfigManager $config;

    private QuestionHelper $questionHelper;

    private ConsoleHelper $consoleHelper;

    protected function configure(): void
    {
        $this
            ->setName('configure')
            ->setDescription('Configure Jira CLI with your credentials and settings')
            ->setHelp(
                'This command helps you set up your Jira URL, email, and API token.' . PHP_EOL .
                'You can generate an API token at: https://id.atlassian.com/manage-profile/security/api-tokens'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->config = new ConfigManager();

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $this->questionHelper = $helper;
        $this->consoleHelper = new ConsoleHelper($output);

        $this->consoleHelper->banner();
        $this->consoleHelper->title('🔧 Jira CLI Configuration');

        // Show current configuration if exists
        if ($this->config->isConfigured()) {
            $this->showCurrentConfig($output);

            $question = new Question('Do you want to reconfigure? (y/N): ', 'n');
            $reconfigure = $this->questionHelper->ask($input, $output, $question);

            if (strtolower($reconfigure) !== 'y') {
                $this->consoleHelper->info('Configuration unchanged.');

                return Command::SUCCESS;
            }
        }

        // Configuration wizard
        try {
            $this->runConfigurationWizard($input, $output);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->consoleHelper->error('Configuration failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function showCurrentConfig(OutputInterface $output): void
    {
        $this->consoleHelper->info('Current Configuration:');
        $output->writeln('');

        $jiraUrl = $this->config->get('jira_url');
        $jiraEmail = $this->config->get('jira_email');
        $jiraToken = $this->config->get('jira_token');
        if ($jiraToken) {
            $maskedToken = str_repeat('*', max(0, strlen($jiraToken) - 4)) . substr($jiraToken, -4);
            $output->writeln("🔑 <info>API Token:</info> {$maskedToken}");
        }
        $output->writeln("📁 <info>Config File:</info> {$this->config->getConfigFile()}");

        $this->consoleHelper->separator();
    }

    private function runConfigurationWizard(InputInterface $input, OutputInterface $output): void
    {
        $this->consoleHelper->info('📝 Let\'s configure your Jira CLI step by step...');
        $this->consoleHelper->separator();

        // Step 1: Jira URL
        $this->consoleHelper->step('1/3', 'Jira Instance URL');
        $jiraUrl = $this->askForJiraUrl($input, $output);

        // Step 2: Email
        $this->consoleHelper->step('2/3', 'Jira Email');
        $jiraEmail = $this->askForEmail($input, $output);

        // Step 3: API Token
        $this->consoleHelper->step('3/3', 'API Token');
        $jiraToken = $this->askForApiToken($input, $output);

        // Test connection
        $this->consoleHelper->separator();
        $this->consoleHelper->info('🔗 Testing connection to Jira...');

        if ($this->testConnection($jiraUrl, $jiraEmail, $jiraToken)) {
            $this->consoleHelper->success('✅ Connection successful!');

            // Save configuration
            $this->config->set('jira_url', $jiraUrl);
            $this->config->set('jira_email', $jiraEmail);
            $this->config->set('jira_token', $jiraToken);

            if ($this->config->save()) {
                $this->consoleHelper->success('✅ Configuration saved successfully!');
                $this->consoleHelper->info("📁 Config saved to: {$this->config->getConfigFile()}");
                $this->consoleHelper->separator();
                $this->consoleHelper->info('🚀 You can now create tickets with: jira-wizard create');
            } else {
                throw new \Exception('Failed to save configuration file');
            }
        } else {
            throw new \Exception('Failed to connect to Jira. Please check your credentials and try again.');
        }
    }

    private function askForJiraUrl(InputInterface $input, OutputInterface $output): string
    {
        $currentUrl = $this->config->get('jira_url');
        $defaultText = $currentUrl ? " (current: {$currentUrl})" : '';

        $this->consoleHelper->info('Enter your Jira instance URL (e.g., https://yourcompany.atlassian.net)');

        $question = new Question("Jira URL{$defaultText}: ", $currentUrl);
        $question->setValidator(function ($value) {
            if (empty($value)) {
                throw new \Exception('Jira URL cannot be empty');
            }

            if (!$this->config->validateJiraUrl($value)) {
                throw new \Exception('Invalid Jira URL. Please use format: https://yourcompany.atlassian.net');
            }

            return rtrim($value, '/');
        });

        return $this->questionHelper->ask($input, $output, $question);
    }

    private function askForEmail(InputInterface $input, OutputInterface $output): string
    {
        $currentEmail = $this->config->get('jira_email');
        $defaultText = $currentEmail ? " (current: {$currentEmail})" : '';

        $this->consoleHelper->info('Enter your Jira account email address');

        $question = new Question("Email{$defaultText}: ", $currentEmail);
        $question->setValidator(function ($value) {
            if (empty($value)) {
                throw new \Exception('Email cannot be empty');
            }

            if (!$this->config->validateEmail($value)) {
                throw new \Exception('Invalid email address');
            }

            return $value;
        });

        return $this->questionHelper->ask($input, $output, $question);
    }

    private function askForApiToken(InputInterface $input, OutputInterface $output): string
    {
        $currentToken = $this->config->get('jira_token');
        $defaultText = $currentToken ? ' (current: ****)' : '';

        $this->consoleHelper->info('Enter your Jira API token');
        $this->consoleHelper->warning('💡 Generate a token at: https://id.atlassian.com/manage-profile/security/api-tokens');

        $question = new Question("API Token{$defaultText}: ", $currentToken);
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $question->setValidator(function ($value) {
            if (empty($value)) {
                throw new \Exception('API Token cannot be empty');
            }

            if (!$this->config->validateToken($value)) {
                throw new \Exception('Invalid API token format');
            }

            return $value;
        });

        return $this->questionHelper->ask($input, $output, $question);
    }

    private function testConnection(string $jiraUrl, string $email, string $token): bool
    {
        try {
            $client = new JiraApiClient($jiraUrl, $email, $token);
            $connected = $client->testConnection();

            if ($connected) {
                // Get user info to verify
                $user = $client->getCurrentUser();
                $this->consoleHelper->success("👋 Hello, {$user['displayName']}!");
            }

            return $connected;
        } catch (\Exception $e) {
            $this->consoleHelper->error("Connection failed: {$e->getMessage()}");

            return false;
        }
    }
}
