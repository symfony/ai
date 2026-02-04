<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

use Symfony\AI\Platform\Result\ToolCall;

/**
 * Default policy that allows read operations and asks for write/exec operations.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DefaultPolicy implements RememberablePolicyInterface
{
    /** @var array<string, PolicyDecision> */
    private array $toolDecisions = [];

    /** @var list<string> */
    private array $alwaysAllow = [];

    /** @var list<string> */
    private array $alwaysDeny = [];

    /** @var list<string> */
    private array $readPatterns = ['read', 'get', 'list', 'search', 'find', 'show', 'describe'];

    public function decide(ToolCall $toolCall): PolicyDecision
    {
        $name = $toolCall->getName();

        if (\in_array($name, $this->alwaysDeny, true)) {
            return PolicyDecision::Deny;
        }

        if (\in_array($name, $this->alwaysAllow, true)) {
            return PolicyDecision::Allow;
        }

        if (isset($this->toolDecisions[$name])) {
            return $this->toolDecisions[$name];
        }

        return $this->inferDecision($toolCall);
    }

    /**
     * Always allow a specific tool.
     */
    public function allow(string $toolName): self
    {
        $this->alwaysAllow[] = $toolName;

        return $this;
    }

    /**
     * Always deny a specific tool.
     */
    public function deny(string $toolName): self
    {
        $this->alwaysDeny[] = $toolName;

        return $this;
    }

    /**
     * Remember a decision for a tool (e.g., after user confirmation).
     */
    public function remember(string $toolName, PolicyDecision $decision): void
    {
        $this->toolDecisions[$toolName] = $decision;
    }

    /**
     * Set patterns that indicate read-only operations (auto-allowed).
     *
     * @param list<string> $patterns
     */
    public function setReadPatterns(array $patterns): self
    {
        $this->readPatterns = $patterns;

        return $this;
    }

    private function inferDecision(ToolCall $toolCall): PolicyDecision
    {
        $words = $this->extractWords($toolCall->getName());

        foreach ($this->readPatterns as $pattern) {
            if (\in_array($pattern, $words, true)) {
                return PolicyDecision::Allow;
            }
        }

        return PolicyDecision::AskUser;
    }

    /**
     * Extracts individual words from a tool name by splitting on underscores,
     * hyphens, and camelCase boundaries.
     *
     * @return list<string>
     */
    private function extractWords(string $name): array
    {
        /** @var list<string> $words */
        $words = preg_split('/[_\-]|(?<=[a-z])(?=[A-Z])/', $name, -1, \PREG_SPLIT_NO_EMPTY);

        return $words;
    }
}
