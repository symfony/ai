<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Speech;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Bridge\OpenAi\TextToSpeech\Voice;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'audio-chat';

    public function __construct(
        #[Autowire(service: 'ai.platform.openai')]
        private readonly PlatformInterface $platform,
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.speech')]
        private readonly AgentInterface $agent,
    ) {
    }

    public function say(string $base64audio): void
    {
        // Convert base64 to temporary binary file
        $path = tempnam(sys_get_temp_dir(), 'audio-').'.wav';
        file_put_contents($path, base64_decode($base64audio));

        $result = $this->platform->invoke('whisper-1', Audio::fromFile($path));

        $this->submitMessage($result->asText());
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
    }

    public function submitMessage(string $message): void
    {
        $messages = $this->loadMessages();

        $messages->add(Message::ofUser($message));
        $result = $this->agent->call($messages);

        \assert($result instanceof TextResult);

        $assistantMessage = Message::ofAssistant($result->getContent());
        $messages->add($assistantMessage);

        $result = $this->platform->invoke('tts-1', $result->getContent(), [
            'voice' => Voice::CORAL,
            'instructions' => 'Speak in a cheerful and positive tone.',
        ]);
        $assistantMessage->getMetadata()->add('speech', $result->asDataUri('audio/mpeg'));

        $this->saveMessages($messages);
    }

    public function reset(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    private function saveMessages(MessageBag $messages): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $messages);
    }
}
