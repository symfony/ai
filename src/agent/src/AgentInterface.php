<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent;

use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface AgentInterface
{
    /**
     * Starts the agent and returns a lazy {@see Execution}.
     *
     * The execution is the result it produces: read it eagerly (`->getContent()`, `->getResult()`), iterate it to
     * observe every progress and result update, or register callbacks (`->onProgress(...)`). A plain string and
     * a single {@see UserMessage} are normalized into a {@see MessageBag}.
     *
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface When the agent encounters an error (e.g., unsupported model capabilities, invalid arguments, network failures, or processor errors)
     */
    public function call(string|MessageBag|UserMessage $input, array $options = []): Execution;

    /**
     * Get the agent's name, which can be used for debugging or multi-agent configuration.
     */
    public function getName(): string;
}
