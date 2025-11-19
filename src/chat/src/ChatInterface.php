<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat;

use Symfony\AI\Agent\Exception\ExceptionInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ChatInterface
{
    public function initiate(MessageBag $messages): void;

    /**
     * @throws ExceptionInterface When the chat submission fails due to agent errors
     */
    public function submit(UserMessage $message): AssistantMessage;
}
