<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\DependencyInjection;

/**
 * Matches MCP elements against the patterns a server lists for a kind of element.
 *
 * A pattern is either the "*" wildcard, an exact service id, an exact class name, or a
 * namespace prefix (recognized by its trailing backslash). Both the service id and the
 * resolved class are checked, because MCP elements can be registered under a service id
 * that is not their class name.
 *
 * Which patterns actually matched something is recorded so {@see McpPass} can report a
 * configured pattern that matches nothing — practically always a typo.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ElementMatcher
{
    public const WILDCARD = '*';

    /**
     * @var array<string, array<string, array<string, true>>> server => kind => pattern => true
     */
    private array $used = [];

    /**
     * @param array<string, array<string, list<string>>> $servers server => kind => patterns
     */
    public function __construct(
        private readonly array $servers,
    ) {
    }

    /**
     * Returns the names of the servers exposing the given element.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    public function match(string $kind, string $serviceId, string $class): array
    {
        $servers = [];

        foreach ($this->servers as $server => $kinds) {
            foreach ($kinds[$kind] ?? [] as $pattern) {
                if (!$this->matches($pattern, $serviceId, $class)) {
                    continue;
                }

                $this->used[$server][$kind][$pattern] = true;
                $servers[] = $server;

                break;
            }
        }

        return $servers;
    }

    /**
     * Returns the configured patterns that did not match any element, as a list of
     * `[server, kind, pattern]` triples. The wildcard is exempt: the configuration already
     * enforces that a server lists something, and "*" legitimately matches nothing when an
     * application has no element of that kind yet.
     *
     * @return list<array{string, string, string}>
     */
    public function getUnusedPatterns(): array
    {
        $unused = [];

        foreach ($this->servers as $server => $kinds) {
            foreach ($kinds as $kind => $patterns) {
                foreach ($patterns as $pattern) {
                    if (self::WILDCARD === $pattern || isset($this->used[$server][$kind][$pattern])) {
                        continue;
                    }

                    $unused[] = [$server, $kind, $pattern];
                }
            }
        }

        return $unused;
    }

    private function matches(string $pattern, string $serviceId, string $class): bool
    {
        if (self::WILDCARD === $pattern) {
            return true;
        }

        if ($pattern === $serviceId || $pattern === $class) {
            return true;
        }

        if (!str_ends_with($pattern, '\\')) {
            return false;
        }

        return str_starts_with($class, $pattern) || str_starts_with($serviceId, $pattern);
    }
}
