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

use App\Conversation\ConversationIds;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class Chat
{
    private const CHAT = 'speech';

    public function __construct(
        private readonly ConversationIds $conversationIds,
        #[Autowire(service: 'ai.message_store.session.conversations')]
        private readonly MessageStoreInterface $messageStore,
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

        $messages = MessageBag::forId($this->conversationIds->get(self::CHAT), Message::ofUser(Audio::fromFile($path)));

        $execution = $this->agent->call($messages);
        $result = $execution->getResult();

        if (!$result instanceof BinaryResult) {
            return;
        }

        $messages = $execution->getMessages();
        $messages->latestAs(Role::Assistant)->getMetadata()->add('speech', $result->toDataUri('audio/mpeg'));

        $this->messageStore->save($messages);
    }

    public function loadMessages(): MessageBag
    {
        return $this->messageStore->load($this->conversationIds->get(self::CHAT));
    }

    public function reset(): void
    {
        $this->messageStore->delete($this->conversationIds->get(self::CHAT));
        $this->conversationIds->reset(self::CHAT);
    }
}
