<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Audio;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\ChatInterface;
use Symfony\AI\Platform\Bridge\OpenAi\Whisper;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('audio')]
final class TwigComponent
{
    use DefaultActionTrait;

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.audio')]
        private readonly AgentInterface $agent,
        #[Autowire(service: 'ai.chat.audio')]
        private readonly ChatInterface $chat,
        #[Autowire(service: 'ai.message_store.cache.audio')]
        private readonly MessageStoreInterface $messageStore,
    ) {
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->getCurrentMessageBag()->withoutSystemMessage()->getMessages();
    }

    #[LiveAction]
    public function submit(#[LiveArg] string $audio): void
    {
        // Convert base64 to temporary binary file
        $path = tempnam(sys_get_temp_dir(), 'audio-').'.wav';
        file_put_contents($path, base64_decode($audio));

        $result = $this->platform->invoke(new Whisper(), Audio::fromFile($path));

        $this->chat->submit(Message::ofUser($result->asText()));
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->messageStore->clear();
    }
}
