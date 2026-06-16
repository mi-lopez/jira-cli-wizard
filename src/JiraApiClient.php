<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class JiraApiClient
{
    private Client $client;

    private string $baseUrl;

    private string $email;

    private string $token;

    public function __construct(string $baseUrl, string $email, string $token)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->email = $email;
        $this->token = $token;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'auth' => [$this->email, $this->token],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Test connection to Jira API.
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('/rest/api/3/myself');

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Get current user information.
     */
    public function getCurrentUser(): array
    {
        try {
            $response = $this->client->get('/rest/api/3/myself');

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new \Exception('Failed to get current user: ' . $e->getMessage());
        }
    }

    /**
     * Get all projects accessible to user.
     */
    public function getProjects(): array
    {
        try {
            $response = $this->client->get('/rest/api/3/project/search', [
                'query' => [
                    'expand' => 'lead,description,projectKeys',
                    'maxResults' => 100,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['values'] ?? [];
        } catch (RequestException $e) {
            throw new \Exception('Failed to get projects: ' . $e->getMessage());
        }
    }

    /**
     * Get active sprint for a project.
     */
    public function getActiveSprint(string $projectKey): ?array
    {
        try {
            $response = $this->client->get('/rest/agile/1.0/board', [
                'query' => [
                    'projectKeyOrId' => $projectKey,
                    'type' => 'scrum',
                ],
            ]);

            $boards = json_decode($response->getBody()->getContents(), true);

            if (empty($boards['values'])) {
                return null;
            }

            $boardId = $boards['values'][0]['id'];

            // Get active sprints for this board
            $response = $this->client->get("/rest/agile/1.0/board/{$boardId}/sprint", [
                'query' => [
                    'state' => 'active',
                ],
            ]);

            $sprints = json_decode($response->getBody()->getContents(), true);

            return $sprints['values'][0] ?? null;
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * Get epics for a project.
     */
    public function getEpics(string $projectKey): array
    {
        try {
            $jql = "project = {$projectKey} AND type = Epic ORDER BY updated DESC";

            $response = $this->client->get('/rest/api/3/search/jql', [
                'query' => [
                    'jql' => $jql,
                    'fields' => 'key,summary,status',
                    'maxResults' => 20,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['issues'] ?? [];
        } catch (RequestException $e) {
            return [];
        }
    }

    /**
     * Get assignable users for a project.
     */
    public function getAssignableUsers(string $projectKey): array
    {
        try {
            $response = $this->client->get('/rest/api/3/user/assignable/search', [
                'query' => [
                    'project' => $projectKey,
                    'maxResults' => 50,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return [];
        }
    }

    /**
     * Get issue types for a project.
     */
    public function getIssueTypes(string $projectKey): array
    {
        try {
            $response = $this->client->get("/rest/api/3/project/{$projectKey}");
            $project = json_decode($response->getBody()->getContents(), true);

            return $project['issueTypes'] ?? [];
        } catch (RequestException $e) {
            return [];
        }
    }

    /**
     * Get priorities.
     */
    public function getPriorities(): array
    {
        try {
            $response = $this->client->get('/rest/api/3/priority');

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return [];
        }
    }

    /**
     * Create a new issue.
     */
    public function createIssue(array $issueData): array
    {
        try {
            $response = $this->client->post('/rest/api/3/issue', [
                'json' => $issueData,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $errorBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            throw new \Exception('Failed to create issue: ' . $e->getMessage() . "\nResponse: " . $errorBody);
        }
    }

    /**
     * Update an existing issue (partial update of fields).
     */
    public function updateIssue(string $issueKey, array $fields): bool
    {
        try {
            $this->client->put("/rest/api/3/issue/{$issueKey}", [
                'json' => ['fields' => $fields],
            ]);

            return true;
        } catch (RequestException $e) {
            $errorBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
            throw new \Exception('Failed to update issue: ' . $e->getMessage() . "\nResponse: " . $errorBody);
        }
    }

    /**
     * Add a comment to an issue. Expects an ADF doc as the comment body.
     */
    public function addComment(string $issueKey, array $adfDoc): bool
    {
        try {
            $this->client->post("/rest/api/3/issue/{$issueKey}/comment", [
                'json' => ['body' => $adfDoc],
            ]);

            return true;
        } catch (RequestException $e) {
            return false;
        }
    }

    /**
     * Get issue by key.
     */
    public function getIssue(string $issueKey): ?array
    {
        try {
            $response = $this->client->get("/rest/api/3/issue/{$issueKey}", [
                'query' => [
                    'expand' => 'names,schema,operations,editmeta,changelog,renderedFields',
                    'fields' => 'summary,description,issuetype,priority,assignee,project,parent,sprint,status,labels',
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * Add issue to sprint.
     */
    public function addIssueToSprint(string $issueKey, int $sprintId): bool
    {
        try {
            $this->client->post("/rest/agile/1.0/sprint/{$sprintId}/issue", [
                'json' => [
                    'issues' => [$issueKey],
                ],
            ]);

            return true;
        } catch (RequestException $e) {
            return false;
        }
    }
}
