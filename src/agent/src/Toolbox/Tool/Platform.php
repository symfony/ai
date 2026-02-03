<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Platform\PlatformInterface;

/**
 * Wraps a Platform instance as a tool, allowing agents to use specialized platforms for specific tasks.
 *
 * This enables scenarios where an agent can leverage different models or platforms
 * as tools (e.g., using gpt-4o for complex calculations while using gpt-4o-mini for the main agent).
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final readonly class Platform
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        private array $options = [],
    ) {
    }

    /**
     * @param string $message the message to pass to the chain
     */
    public function __invoke(string $message): string
    {
        return $this->platform->invoke(
            $this->model,
            $message,
            $this->options,
        )->asText();
    }
}
