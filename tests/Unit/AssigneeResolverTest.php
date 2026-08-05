<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Tests\Unit;

use MiLopez\JiraCliWizard\Helpers\AssigneeResolver;
use PHPUnit\Framework\TestCase;

class AssigneeResolverTest extends TestCase
{
    /**
     * @return array<int, array<string, string>>
     */
    private function users(): array
    {
        return [
            ['accountId' => 'acc-1', 'displayName' => 'Ada Lovelace', 'emailAddress' => 'ada@example.com'],
            ['accountId' => 'acc-2', 'displayName' => 'Alan Turing', 'emailAddress' => 'alan@example.com'],
            ['accountId' => 'acc-3', 'displayName' => 'Grace Hopper', 'emailAddress' => 'grace@example.com'],
        ];
    }

    public function testExactDisplayNameMatch(): void
    {
        $this->assertSame('acc-2', AssigneeResolver::resolve($this->users(), 'Alan Turing'));
    }

    public function testExactDisplayNameMatchIsCaseInsensitive(): void
    {
        $this->assertSame('acc-2', AssigneeResolver::resolve($this->users(), 'alan turing'));
    }

    public function testExactEmailMatch(): void
    {
        $this->assertSame('acc-3', AssigneeResolver::resolve($this->users(), 'grace@example.com'));
    }

    public function testExactEmailMatchIsCaseInsensitive(): void
    {
        $this->assertSame('acc-3', AssigneeResolver::resolve($this->users(), 'GRACE@EXAMPLE.COM'));
    }

    public function testDirectAccountIdMatch(): void
    {
        $this->assertSame('acc-1', AssigneeResolver::resolve($this->users(), 'acc-1'));
    }

    public function testSinglePartialMatchResolves(): void
    {
        $this->assertSame('acc-3', AssigneeResolver::resolve($this->users(), 'Hopper'));
    }

    public function testPartialMatchAlsoLooksAtEmail(): void
    {
        $this->assertSame('acc-1', AssigneeResolver::resolve($this->users(), 'ada@'));
    }

    public function testAmbiguousPartialMatchIsRejectedRatherThanGuessed(): void
    {
        // "a" appears in several names; silently taking the first would assign
        // the ticket to the wrong person with no warning.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Multiple assignees found matching 'a'");

        AssigneeResolver::resolve($this->users(), 'a');
    }

    public function testAmbiguousMatchListsTheCandidates(): void
    {
        try {
            AssigneeResolver::resolve($this->users(), 'a');
            $this->fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Ada Lovelace', $e->getMessage());
            $this->assertStringContainsString('Alan Turing', $e->getMessage());
        }
    }

    public function testExactMatchWinsOverBeingASubstringOfSomeoneElse(): void
    {
        $users = [
            ['accountId' => 'acc-1', 'displayName' => 'Ann', 'emailAddress' => 'ann@example.com'],
            ['accountId' => 'acc-2', 'displayName' => 'Anna', 'emailAddress' => 'anna@example.com'],
        ];

        // "Ann" is a substring of "Anna", so without the exact-match pass first
        // this would be ambiguous and throw.
        $this->assertSame('acc-1', AssigneeResolver::resolve($users, 'Ann'));
    }

    public function testNoMatchThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No assignee found matching 'Nobody'");

        AssigneeResolver::resolve($this->users(), 'Nobody');
    }

    public function testEmptyUserListThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No assignable users found');

        AssigneeResolver::resolve([], 'Ada Lovelace');
    }

    public function testMissingDisplayNameOrEmailKeysDoNotFatal(): void
    {
        $users = [
            ['accountId' => 'acc-1'],
            ['accountId' => 'acc-2', 'displayName' => 'Grace Hopper'],
        ];

        $this->assertSame('acc-2', AssigneeResolver::resolve($users, 'Grace Hopper'));
        $this->assertSame('acc-1', AssigneeResolver::resolve($users, 'acc-1'));
    }

    public function testEmptyQueryDoesNotPartialMatchEveryone(): void
    {
        // stripos(..., '') returns 0, which is !== false -- so an empty query
        // would otherwise "partially match" every user and report them all as
        // ambiguous rather than saying nothing was found.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No assignee found');

        AssigneeResolver::resolve($this->users(), '');
    }
}
