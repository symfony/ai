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
use Symfony\AI\Platform\Result\AudioResult;
use Symfony\AI\Platform\Result\ImageResult;
use Symfony\AI\Platform\Result\TextResult;

/**
 * Wraps a Platform instance as a tool, allowing agents to use specialized platforms for specific tasks.
 *
 * This enables scenarios where an agent using one platform (e.g., OpenAI) can leverage
 * another platform (e.g., ElevenLabs for speech-to-text) as a tool.
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
     * @param array<mixed>|string|object $input the input to pass to the platform
     */
    public function __invoke(array|string|object $input): string
    {
        $result = $this->platform->invoke(
            $this->model,
            $input,
            $this->options,
        )->await();

        return match (true) {
            $result instanceof TextResult => $result->getContent(),
            $result instanceof AudioResult => $result->getText() ?? base64_encode($result->getAudio()),
            $result instanceof ImageResult => $result->getText() ?? base64_encode($result->getImage()),
            default => throw new \LogicException(\sprintf('Unsupported result type "%s".', $result::class)),
        };
    }
}
