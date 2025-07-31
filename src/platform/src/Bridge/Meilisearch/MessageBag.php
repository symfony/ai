<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Meilisearch;

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\InitializableMessageBagInterface;
use Symfony\AI\Platform\Message\MessageBag as InMemoryMessageBag;
use Symfony\AI\Platform\Message\MessageBagInterface;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class MessageBag implements InitializableMessageBagInterface, MessageBagInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $apiKey,
        private string $indexName,
    ) {
    }

    public function add(MessageInterface $message): void
    {
        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), $this->convertToIndexableArray($message));
    }

    public function getMessages(): array
    {
        $messages = $this->request('POST', \sprintf('indexes/%s/documents/fetch', $this->indexName));

        return array_map(
            fn (array $message): MessageInterface => $this->convertToMessage($message),
            $messages['results'],
        );
    }

    public function getSystemMessage(): ?SystemMessage
    {
        $messages = $this->getMessages();

        foreach ($messages as $message) {
            if ($message instanceof SystemMessage) {
                return $message;
            }
        }

        return null;
    }

    public function with(MessageInterface $message): MessageBagInterface
    {
        $this->add($message);

        return $this;
    }

    public function merge(MessageBagInterface $messageBag): MessageBagInterface
    {
        $messages = $messageBag->getMessages();

        foreach ($messages as $message) {
            $this->add($message);
        }

        return $this;
    }

    public function withoutSystemMessage(): MessageBagInterface
    {
        $messages = $this->request('POST', \sprintf('indexes/%s/documents', $this->indexName), [
            'filter' => \sprintf('type != "%s"', SystemMessage::class),
        ]);

        return new InMemoryMessageBag(...array_map(
            fn (array $message): MessageInterface => $this->convertToMessage($message),
            $messages['results']),
        );
    }

    public function prepend(MessageInterface $message): MessageBagInterface
    {
        $this->add($message);

        return $this;
    }

    public function containsAudio(): bool
    {
        foreach ($this->getMessages() as $message) {
            if ($message instanceof UserMessage && $message->hasAudioContent()) {
                return true;
            }
        }

        return false;
    }

    public function containsImage(): bool
    {
        foreach ($this->getMessages() as $message) {
            if ($message instanceof UserMessage && $message->hasImageContent()) {
                return true;
            }
        }

        return false;
    }

    public function initialize(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);
    }

    public function count(): int
    {
        return \count($this->getMessages());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $url = \sprintf('%s/%s', $this->endpointUrl, $endpoint);
        $result = $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => $payload,
        ]);

        return $result->toArray();
    }

    private function convertToIndexableArray(MessageInterface $message): array
    {
        return [
            'id' => $message->getId()->toRfc4122(),
            'type' => $message::class,
            'content' => ($message instanceof SystemMessage || $message instanceof AssistantMessage) ? $message->content : '',
            'contentAsBase64' => ($message instanceof UserMessage && [] !== $message->content) ? array_map(
                static fn (ContentInterface $content) => [
                    'type' => $content::class,
                    'content' => match ($content::class) {
                        Text::class => $content->text,
                        File::class,
                        Image::class,
                        Audio::class => $content->asBase64(),
                        ImageUrl::class,
                        DocumentUrl::class => $content->url,
                        default => throw new \LogicException(\sprintf('Unknown content type "%s".', $content::class)),
                    },
                ],
                $message->content,
            ) : [],
            'toolsCalls' => ($message instanceof AssistantMessage && $message->hasToolCalls()) ? $message->toolCalls : [],
        ];
    }

    private function convertToMessage(array $payload): MessageInterface
    {
        $type = $payload['type'];
        $content = $payload['content'] ?? '';
        $contentAsBase64 = $payload['contentAsBase64'] ?? [];
        $toolsCalls = $payload['toolsCalls'] ?? [];

        return match ($type) {
            SystemMessage::class => new SystemMessage($content),
            AssistantMessage::class => new AssistantMessage($content, $toolsCalls),
            UserMessage::class => new UserMessage(...array_map(
                static fn (array $contentAsBase64) => $contentAsBase64['type']::fromDataUrl($contentAsBase64['content']),
                $contentAsBase64,
            )),
            default => throw new \LogicException(\sprintf('Unknown message type "%s".', $type)),
        };
    }
}
