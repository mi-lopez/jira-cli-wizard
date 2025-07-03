<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard;

class ConfigManager
{
    private string $configFile;

    private array $config = [];

    public function __construct(?string $configFile = null)
    {
        if ($configFile) {
            $this->configFile = $configFile;
        } else {
            $homeDir = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? getcwd();
            $this->configFile = $homeDir . '/.jira-cli-config.json';
        }
        $this->loadConfig();
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['jira_url'])
            && !empty($this->config['jira_email'])
            && !empty($this->config['jira_token']);
    }

    public function get(string $key): ?string
    {
        return $this->config[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        $this->config[$key] = $value;
    }

    public function save(): bool
    {
        $jsonData = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($jsonData === false) {
            return false;
        }

        return file_put_contents($this->configFile, $jsonData) !== false;
    }

    public function getAll(): array
    {
        return $this->config;
    }

    public function clear(): bool
    {
        $this->config = [];

        if (file_exists($this->configFile)) {
            return unlink($this->configFile);
        }

        return true;
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }

    private function loadConfig(): void
    {
        if (!file_exists($this->configFile)) {
            $this->config = [];

            return;
        }

        $content = file_get_contents($this->configFile);

        if ($content === false) {
            $this->config = [];

            return;
        }

        $decoded = json_decode($content, true);
        $this->config = $decoded ?? [];
    }

    public function validateJiraUrl(string $url): bool
    {
        // Remove trailing slash
        $url = rtrim($url, '/');

        // Check if it's a valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's HTTPS (Jira Cloud requires HTTPS)
        if (!str_starts_with($url, 'https://')) {
            return false;
        }

        // Check if it looks like a Jira URL
        if (!str_contains($url, 'atlassian.net') && !str_contains($url, 'jira')) {
            return false;
        }

        return true;
    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function validateToken(string $token): bool
    {
        // Basic token validation (not empty, reasonable length)
        return !empty(trim($token)) && strlen($token) >= 10;
    }
}
