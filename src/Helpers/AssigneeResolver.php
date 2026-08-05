<?php

declare(strict_types=1);

namespace MiLopez\JiraCliWizard\Helpers;

/**
 * Maps whatever the user typed for an assignee -- display name, email address or
 * raw account id -- onto a Jira accountId.
 *
 * This is deliberately a pure function over an already-fetched user list: the
 * matching rules are the part worth testing, and keeping them free of the API
 * client means they can be tested without one.
 */
class AssigneeResolver
{
    /**
     * @param array<int, array<string, mixed>> $users as returned by JiraApiClient::getAssignableUsers()
     *
     * @throws \InvalidArgumentException when the list is empty, the query matches nothing,
     *                                   or a partial query matches more than one person
     */
    public static function resolve(array $users, string $query): string
    {
        if ($users === []) {
            throw new \InvalidArgumentException('No assignable users found for this project.');
        }

        // Exact match on display name or email wins outright, so someone whose
        // name is a substring of a colleague's is still addressable.
        foreach ($users as $user) {
            $displayName = (string) ($user['displayName'] ?? '');
            $email = (string) ($user['emailAddress'] ?? '');

            if (strcasecmp($displayName, $query) === 0 || strcasecmp($email, $query) === 0) {
                return (string) $user['accountId'];
            }
        }

        foreach ($users as $user) {
            if ((string) ($user['accountId'] ?? '') === $query) {
                return (string) $user['accountId'];
            }
        }

        $partial = [];
        foreach ($users as $user) {
            $displayName = (string) ($user['displayName'] ?? '');
            $email = (string) ($user['emailAddress'] ?? '');

            if ($query !== '' && (stripos($displayName, $query) !== false || stripos($email, $query) !== false)) {
                $partial[] = $user;
            }
        }

        if (count($partial) === 1) {
            return (string) $partial[0]['accountId'];
        }

        // Refusing an ambiguous match is the whole point: silently taking the
        // first one assigns the ticket to the wrong person with no warning.
        if (count($partial) > 1) {
            $names = array_map(
                static fn (array $user): string => (string) ($user['displayName'] ?? $user['emailAddress'] ?? $user['accountId']),
                $partial
            );

            throw new \InvalidArgumentException("Multiple assignees found matching '{$query}': " . implode(', ', $names));
        }

        throw new \InvalidArgumentException("No assignee found matching '{$query}'.");
    }
}
