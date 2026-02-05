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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type ChatData array{
 *      action: string,
 *      bag?: MessageBag,
 *      message?: UserMessage,
 *      saved_at: \DateTimeImmutable,
 *  }
 */
final class TraceableChat implements ChatInterface, ResetInterface
{
    /**
     * @var array<int, array{
     *     action: string,
     *     bag?: MessageBag,
     *     message?: UserMessage,
     *     saved_at: \DateTimeImmutable,
     * }>
     */
    public array $calls = [];

    public function __construct(
        private readonly ChatInterface $chat,
        private readonly ClockInterface $clock,
    ) {
    }

    public function initiate(MessageBag $messages): void
    {
        $this->calls[] = [
            'action' => __FUNCTION__,
            'bag' => $messages,
            'saved_at' => $this->clock->now(),
        ];

        $this->chat->initiate($messages);
    }

    public function submit(UserMessage $message): AssistantMessage
    {
        $this->calls[] = [
            'action' => __FUNCTION__,
            'message' => $message,
            'saved_at' => $this->clock->now(),
        ];

        return $this->chat->submit($message);
    }

    public function reset(): void
    {
        if ($this->chat instanceof ResetInterface) {
            $this->chat->reset();
        }
        $this->calls = [];
    }
}
