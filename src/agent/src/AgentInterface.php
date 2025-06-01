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

use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Response\ResponseInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface AgentInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function call(MessageBagInterface $messages, array $options = []): ResponseInterface;
}
