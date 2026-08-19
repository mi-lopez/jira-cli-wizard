<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use MiLopez\JiraCliWizard\JiraApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class JiraApiClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    /**
     * Builds a client whose transport is a queue of canned responses, so the
     * suite never opens a socket. Requests are recorded for assertion.
     *
     * @param array<int, Response|\Throwable> $responses
     */
    private function clientWith(array $responses): JiraApiClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new JiraApiClient(
            'https://test.atlassian.net',
            'test@example.com',
            'test-token',
            new Client(['handler' => $stack, 'base_uri' => 'https://test.atlassian.net'])
        );
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[count($this->history) - 1]['request'];
    }

    public function testConstructorSetsProperties(): void
    {
        $this->assertInstanceOf(JiraApiClient::class, $this->clientWith([]));
    }

    public function testUpdateIssuePutsToTheIssueEndpoint(): void
    {
        $client = $this->clientWith([new Response(204)]);

        $result = $client->updateIssue('ALDO-7', ['fields' => ['summary' => 'New title']]);

        $this->assertTrue($result);
        $this->assertSame('PUT', $this->lastRequest()->getMethod());
        $this->assertSame('/rest/api/3/issue/ALDO-7', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['fields' => ['summary' => 'New title']],
            json_decode((string) $this->lastRequest()->getBody(), true)
        );
    }

    public function testUpdateIssueReturnsFalseWhenJiraDoesNotAnswer204(): void
    {
        // Jira answers 204 on a successful edit; anything else means the edit
        // did not take, even though Guzzle treats any 2xx as a success.
        $client = $this->clientWith([new Response(200)]);

        $this->assertFalse($client->updateIssue('ALDO-7', ['fields' => []]));
    }

    public function testUpdateIssueWrapsTransportErrorsWithTheResponseBody(): void
    {
        $client = $this->clientWith([
            new RequestException(
                'Bad Request',
                new Request('PUT', '/rest/api/3/issue/ALDO-7'),
                new Response(400, [], '{"errorMessages":["Field summary is required"]}')
            ),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to update issue ALDO-7');

        $client->updateIssue('ALDO-7', ['fields' => []]);
    }

    public function testUploadAttachmentRejectsAMissingFileBeforeCallingJira(): void
    {
        $client = $this->clientWith([]);

        try {
            $client->uploadAttachment('ALDO-7', '/nonexistent/nope.png');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('File not found: /nonexistent/nope.png', $e->getMessage());
        }

        $this->assertSame([], $this->history, 'No request should have been attempted.');
    }

    public function testUploadAttachmentPostsMultipartWithTheAntiCsrfHeader(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jira-attach-');
        file_put_contents($file, 'log contents');

        try {
            $client = $this->clientWith([new Response(200, [], '[{"id":"10001"}]')]);

            $result = $client->uploadAttachment('ALDO-7', $file);

            $this->assertSame('10001', $result[0]['id']);

            $request = $this->lastRequest();
            $this->assertSame('POST', $request->getMethod());
            $this->assertSame('/rest/api/3/issue/ALDO-7/attachments', $request->getUri()->getPath());

            // Jira rejects attachment uploads that omit this header.
            $this->assertSame('no-check', $request->getHeaderLine('X-Atlassian-Token'));
            $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));

            $body = (string) $request->getBody();
            $this->assertStringContainsString(basename($file), $body);
            $this->assertStringContainsString('log contents', $body);
        } finally {
            @unlink($file);
        }
    }

    public function testTestConnectionIsTrueOnlyOn200(): void
    {
        $this->assertTrue($this->clientWith([new Response(200)])->testConnection());
        $this->assertFalse($this->clientWith([new Response(202)])->testConnection());
    }

    public function testTestConnectionIsFalseWhenTheRequestBlowsUp(): void
    {
        $client = $this->clientWith([
            new RequestException('Unauthorized', new Request('GET', '/rest/api/3/myself'), new Response(401)),
        ]);

        $this->assertFalse($client->testConnection());
    }

    public function testGetProjectsUnwrapsTheValuesKey(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"values":[{"key":"ALDO"},{"key":"TRIG"}]}')]);

        $this->assertSame([['key' => 'ALDO'], ['key' => 'TRIG']], $client->getProjects());
    }

    public function testGetProjectsReturnsEmptyWhenThePayloadHasNoValues(): void
    {
        $client = $this->clientWith([new Response(200, [], '{}')]);

        $this->assertSame([], $client->getProjects());
    }

    public function testGetProjectsThrowsOnTransportFailure(): void
    {
        $client = $this->clientWith([
            new RequestException('Boom', new Request('GET', '/rest/api/3/project/search')),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to get projects');

        $client->getProjects();
    }

    public function testGetIssueTypesReadsThemOffTheProject(): void
    {
        $client = $this->clientWith([new Response(200, [], '{"issueTypes":[{"id":"1","name":"Task"}]}')]);

        $this->assertSame([['id' => '1', 'name' => 'Task']], $client->getIssueTypes('ALDO'));
        $this->assertSame('/rest/api/3/project/ALDO', $this->lastRequest()->getUri()->getPath());
    }

    public function testLookupsDegradeToAnEmptyListRatherThanThrowing(): void
    {
        // These feed interactive pickers, where an empty list is a usable state
        // and an exception would abort the whole wizard.
        $boom = static fn (string $path) => new RequestException('Boom', new Request('GET', $path));

        $this->assertSame([], $this->clientWith([$boom('/rest/api/3/project/ALDO')])->getIssueTypes('ALDO'));
        $this->assertSame([], $this->clientWith([$boom('/rest/api/3/user/assignable/search')])->getAssignableUsers('ALDO'));
        $this->assertSame([], $this->clientWith([$boom('/rest/api/3/priority')])->getPriorities());
    }

    public function testGetAssignableUsersPassesTheProjectAsAQueryParameter(): void
    {
        $client = $this->clientWith([new Response(200, [], '[{"accountId":"acc-1"}]')]);

        $this->assertSame([['accountId' => 'acc-1']], $client->getAssignableUsers('ALDO'));
        $this->assertStringContainsString('project=ALDO', urldecode($this->lastRequest()->getUri()->getQuery()));
    }

    public function testCreateIssuePostsThePayloadAndReturnsTheNewKey(): void
    {
        $client = $this->clientWith([new Response(201, [], '{"key":"ALDO-99","id":"10099"}')]);

        $result = $client->createIssue(['fields' => ['summary' => 'Hello']]);

        $this->assertSame('ALDO-99', $result['key']);
        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/rest/api/3/issue', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['fields' => ['summary' => 'Hello']],
            json_decode((string) $this->lastRequest()->getBody(), true)
        );
    }

    public function testCreateIssueSurfacesJirasValidationBody(): void
    {
        // Jira explains *why* it rejected a create in the body; losing that turns
        // a fixable error into a mystery.
        $client = $this->clientWith([
            new RequestException(
                'Bad Request',
                new Request('POST', '/rest/api/3/issue'),
                new Response(400, [], '{"errors":{"summary":"Summary is required"}}')
            ),
        ]);

        try {
            $client->createIssue(['fields' => []]);
            $this->fail('Expected an exception.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Failed to create issue', $e->getMessage());
            $this->assertStringContainsString('Summary is required', $e->getMessage());
        }
    }

    public function testAddIssueToSprintUsesTheAgileEndpoint(): void
    {
        $client = $this->clientWith([new Response(204)]);

        $this->assertTrue($client->addIssueToSprint('ALDO-7', 42));
        $this->assertSame('/rest/agile/1.0/sprint/42/issue', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(['issues' => ['ALDO-7']], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function testAddIssueToSprintReportsFailureWithoutThrowing(): void
    {
        // The ticket already exists by this point, so a sprint failure must not
        // take down the command that just created it.
        $client = $this->clientWith([
            new RequestException('Boom', new Request('POST', '/rest/agile/1.0/sprint/42/issue')),
        ]);

        $this->assertFalse($client->addIssueToSprint('ALDO-7', 42));
    }

    public function testGetIssueReturnsNullWhenJiraRepliesNotFound(): void
    {
        $client = $this->clientWith([
            new RequestException(
                'Not Found',
                new Request('GET', '/rest/api/3/issue/INVALID-999'),
                new Response(404)
            ),
        ]);

        $this->assertNull($client->getIssue('INVALID-999'));
    }

    public function testGetTransitionsReturnsTheWorkflowRows(): void
    {
        $client = $this->clientWith([new Response(200, [], json_encode([
            'transitions' => [['id' => '2', 'name' => 'En cours', 'to' => ['name' => 'En cours']]],
        ]))]);

        $transitions = $client->getTransitions('ALDO-7');

        $this->assertSame('2', $transitions[0]['id']);
        $this->assertStringContainsString('/rest/api/3/issue/ALDO-7/transitions', (string) $this->lastRequest()->getUri());
    }

    public function testGetTransitionsReturnsNothingWhenJiraRefuses(): void
    {
        $client = $this->clientWith([
            new RequestException(
                'Forbidden',
                new Request('GET', '/rest/api/3/issue/ALDO-7/transitions'),
                new Response(403)
            ),
        ]);

        $this->assertSame([], $client->getTransitions('ALDO-7'));
    }

    public function testTransitionIssuePostsTheTransitionId(): void
    {
        $client = $this->clientWith([new Response(204)]);

        $this->assertTrue($client->transitionIssue('ALDO-7', '281'));

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(
            ['transition' => ['id' => '281']],
            json_decode((string) $request->getBody(), true)
        );
    }

    public function testGetIssueCommentsReturnsTheCommentRows(): void
    {
        $client = $this->clientWith([new Response(200, [], json_encode([
            'startAt' => 0,
            'maxResults' => 100,
            'total' => 1,
            'comments' => [['id' => '1', 'author' => ['displayName' => 'Ada']]],
        ]))]);

        $comments = $client->getIssueComments('ALDO-7');

        $this->assertCount(1, $comments);
        $this->assertSame('Ada', $comments[0]['author']['displayName']);
        $this->assertStringContainsString('/rest/api/3/issue/ALDO-7/comment', (string) $this->lastRequest()->getUri());
    }

    public function testGetIssueCommentsWalksEveryPage(): void
    {
        // A busy ticket outgrows Jira's page size; stopping at the first page
        // would silently hide the newest comments.
        $page = static fn (int $startAt, array $ids) => new Response(200, [], json_encode([
            'startAt' => $startAt,
            'maxResults' => 100,
            'total' => 3,
            'comments' => array_map(static fn (string $id) => ['id' => $id], $ids),
        ]));

        $client = $this->clientWith([$page(0, ['1', '2']), $page(2, ['3'])]);

        $this->assertCount(3, $client->getIssueComments('ALDO-7'));
    }

    public function testGetIssueCommentsReturnsWhatItHasWhenJiraFails(): void
    {
        $client = $this->clientWith([
            new RequestException(
                'Boom',
                new Request('GET', '/rest/api/3/issue/ALDO-7/comment'),
                new Response(500)
            ),
        ]);

        $this->assertSame([], $client->getIssueComments('ALDO-7'));
    }

    public function testGetIssueRequestsTheLabelsField(): void
    {
        // create-from prefills labels from the template ticket, which only works
        // if labels are in the requested field list.
        $client = $this->clientWith([new Response(200, [], '{"key":"ALDO-7","fields":{"labels":["backend"]}}')]);

        $issue = $client->getIssue('ALDO-7');

        $this->assertSame(['backend'], $issue['fields']['labels']);
        $this->assertStringContainsString('labels', urldecode($this->lastRequest()->getUri()->getQuery()));
    }
}
