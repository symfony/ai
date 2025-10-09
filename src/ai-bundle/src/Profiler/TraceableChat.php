<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Chat\ChatInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type MessageStoreData array{
 *      bag: MessageBag,
 *  }
 */
final class TraceableChat implements ChatInterface
{
    /**
     * @var array
     */
    public array $calls = [];

    public function __construct(
        private readonly ChatInterface $chat,
    ) {
    }

    public function initiate(MessageBag $messages): void
    {
        // TODO: Implement initiate() method.
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        // TODO: Implement submit() method.
    }
}
