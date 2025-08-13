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
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface AgentInterface
{
    /**
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface When the agent encounters an error (e.g., unsupported model capabilities, invalid arguments, network failures, or processor errors)
     */
    public function call(MessageBagInterface $messages, array $options = []): ResultInterface;
}
