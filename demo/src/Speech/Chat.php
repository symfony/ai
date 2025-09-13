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
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

final class Chat
{
    private const SESSION_KEY = 'audio-chat';

    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(service: 'ai.agent.speech')]
        private readonly AgentInterface $agent,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function say(string $base64audio): void
    {
        // Convert base64 to temporary binary file
        $path = $this->filesystem->tempnam(sys_get_temp_dir(), 'audio-', '.wav');
        $this->filesystem->dumpFile($path, base64_decode($base64audio));

        $messages = $this->loadMessages();
        $messages->add(Message::ofUser(Audio::fromFile($path)));

        $result = $this->agent->call($messages);

        $text = $result->getMetadata()->get('text');
        $assistantMessage = Message::ofAssistant($text);
        $messages->add($assistantMessage);

        if ($result instanceof BinaryResult) {
            $assistantMessage->getMetadata()->add('speech', $result->toDataUri('audio/mpeg'));
        }

        $this->saveMessages($messages);
    }

    public function loadMessages(): MessageBag
    {
        return $this->requestStack->getSession()->get(self::SESSION_KEY, new MessageBag());
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
