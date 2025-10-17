<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Meilisearch;

use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\Exception\LogicException;
use Symfony\AI\Chat\Exception\RuntimeException;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\DocumentUrl;
use Symfony\AI\Platform\Message\Content\File;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\ImageUrl;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\SystemMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $endpointUrl,
        #[\SensitiveParameter] private string $apiKey,
        private ClockInterface $clock,
        private string $indexName = '_message_store_meilisearch',
    ) {
        if (!interface_exists(ClockInterface::class)) {
            throw new RuntimeException('For using Meilisearch as a message store , symfony/clock is required. Try running "composer require symfony/clock".');
        }
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('POST', 'indexes', [
            'uid' => $this->indexName,
            'primaryKey' => 'id',
        ]);

        $this->request('PATCH', \sprintf('indexes/%s/settings', $this->indexName), [
            'sortableAttributes' => [
                'addedAt',
            ],
        ]);
    }

    public function save(MessageBag $messages): void
    {
        $messages = $messages->getMessages();

        $this->request('PUT', \sprintf('indexes/%s/documents', $this->indexName), array_map(
            $this->convertToIndexableArray(...),
            $messages,
        ));
    }

    public function load(): MessageBag
    {
        $messages = $this->request('POST', \sprintf('indexes/%s/documents/fetch', $this->indexName), [
            'sort' => ['addedAt:asc'],
        ]);

        return new MessageBag(...array_map($this->convertToMessage(...), $messages['results']));
    }

    public function drop(): void
    {
        $this->request('DELETE', \sprintf('indexes/%s/documents', $this->indexName));
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $result = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpointUrl, $endpoint), [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        $payload = $result->toArray();

        if (!\array_key_exists('status', $payload)) {
            return $payload;
        }

        if (\in_array($payload['status'], ['succeeded', 'failed'], true)) {
            return $payload;
        }

        $currentTaskStatusCallback = fn (): ResponseInterface => $this->httpClient->request('GET', \sprintf('%s/tasks/%s', $this->endpointUrl, $payload['taskUid']), [
            'headers' => [
                'Authorization' => \sprintf('Bearer %s', $this->apiKey),
            ],
        ]);

        while ('succeeded' !== $currentTaskStatusCallback()->toArray()['status']) {
            $this->clock->sleep(1);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function convertToIndexableArray(MessageInterface $message): array
    {
        $toolsCalls = [];

        if ($message instanceof AssistantMessage && $message->hasToolCalls()) {
            $toolsCalls = array_map(
                static fn (ToolCall $toolCall): array => $toolCall->jsonSerialize(),
                $message->getToolCalls(),
            );
        }

        if ($message instanceof ToolCallMessage) {
            $toolsCalls = $message->getToolCall()->jsonSerialize();
        }

        return [
            'id' => $message->getId()->toRfc4122(),
            'type' => $message::class,
            'content' => ($message instanceof SystemMessage || $message instanceof AssistantMessage || $message instanceof ToolCallMessage) ? $message->getContent() : '',
            'contentAsBase64' => ($message instanceof UserMessage && [] !== $message->getContent()) ? array_map(
                static fn (ContentInterface $content) => [
                    'type' => $content::class,
                    'content' => match ($content::class) {
                        Text::class => $content->getText(),
                        File::class,
                        Image::class,
                        Audio::class => $content->asBase64(),
                        ImageUrl::class,
                        DocumentUrl::class => $content->getUrl(),
                        default => throw new LogicException(\sprintf('Unknown content type "%s".', $content::class)),
                    },
                ],
                $message->getContent(),
            ) : [],
            'toolsCalls' => $toolsCalls,
            'metadata' => $message->getMetadata()->all(),
            'addedAt' => (new \DateTimeImmutable())->getTimestamp(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function convertToMessage(array $payload): MessageInterface
    {
        $type = $payload['type'];
        $content = $payload['content'] ?? '';
        $contentAsBase64 = $payload['contentAsBase64'] ?? [];

        $message = match ($type) {
            SystemMessage::class => new SystemMessage($content),
            AssistantMessage::class => new AssistantMessage($content, array_map(
                static fn (array $toolsCall): ToolCall => new ToolCall(
                    $toolsCall['id'],
                    $toolsCall['function']['name'],
                    json_decode($toolsCall['function']['arguments'], true)
                ),
                $payload['toolsCalls'],
            )),
            UserMessage::class => new UserMessage(...array_map(
                static fn (array $contentAsBase64) => \in_array($contentAsBase64['type'], [File::class, Image::class, Audio::class], true)
                    ? $contentAsBase64['type']::fromDataUrl($contentAsBase64['content'])
                    : new $contentAsBase64['type']($contentAsBase64['content']),
                $contentAsBase64,
            )),
            ToolCallMessage::class => new ToolCallMessage(
                new ToolCall(
                    $payload['toolsCalls']['id'],
                    $payload['toolsCalls']['function']['name'],
                    json_decode($payload['toolsCalls']['function']['arguments'], true)
                ),
                $content
            ),
            default => throw new LogicException(\sprintf('Unknown message type "%s".', $type)),
        };

        $message->getMetadata()->set($payload['metadata']);

        return $message;
    }
}
