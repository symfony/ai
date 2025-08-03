<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\YouTube;

use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Chat\MessageStoreInterface;
use Symfony\AI\Agent\ChatInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

use function Symfony\Component\String\u;

#[AsLiveComponent('youtube')]
final class TwigComponent
{
    use DefaultActionTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'ai.chat.youtube')]
        private readonly ChatInterface $chat,
        #[Autowire(service: 'ai.message_store.cache.youtube')]
        private readonly MessageStoreInterface $messageStore,
        private readonly TranscriptFetcher $transcriptFetcher,
    ) {
    }

    #[LiveAction]
    public function start(#[LiveArg] string $videoId): void
    {
        if (str_contains($videoId, 'youtube.com')) {
            $videoId = $this->getVideoIdFromUrl($videoId);
        }

        try {
            $this->doStart($videoId);
        } catch (\Exception $e) {
            $this->logger->error('Unable to start YouTube chat.', ['exception' => $e]);
            $this->messageStore->clear();
        }
    }

    /**
     * @return MessageInterface[]
     */
    public function getMessages(): array
    {
        return $this->chat->getCurrentMessageBag()->withoutSystemMessage()->getMessages();
    }

    #[LiveAction]
    public function submit(#[LiveArg] string $message): void
    {
        $this->chat->submit(Message::ofUser($message));
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->messageStore->clear();
    }

    private function getVideoIdFromUrl(string $url): string
    {
        $query = parse_url($url, \PHP_URL_QUERY);

        if (!$query) {
            throw new \InvalidArgumentException('Unable to parse YouTube URL.');
        }

        return u($query)->after('v=')->before('&')->toString();
    }

    private function doStart(string $videoId): void
    {
        $transcript = $this->transcriptFetcher->fetchTranscript($videoId);
        $system = <<<PROMPT
            You are an helpful assistant that answers questions about a YouTube video based on a transcript.
            If you can't answer a question, say so.

            Video ID: {$videoId}
            Transcript:
            {$transcript}
            PROMPT;

        $messages = new MessageBag(
            Message::forSystem($system),
            Message::ofAssistant('What do you want to know about that video?'),
        );

        $this->reset();
        $this->chat->initiate($messages);
    }
}
