<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Pogocache;

use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\Exception\LogicException;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $host,
        #[\SensitiveParameter] private string $password,
        private string $key = '_message_store_pogocache',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('The Pogocache message store does not support any options.');
        }

        $this->request('PUT', $this->key);
    }

    public function drop(): void
    {
        $this->request('PUT', $this->key);
    }

    public function save(MessageBag $messages): void
    {
        $messages = $messages->getMessages();

        $this->request('PUT', $this->key, array_map(
            $this->convertToIndexableArray(...),
            $messages,
        ));
    }

    public function load(): MessageBag
    {
        $messages = $this->request('GET', $this->key);

        return new MessageBag(...array_map(
            $this->convertToMessage(...),
            $messages,
        ));
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $result = $this->httpClient->request($method, \sprintf('%s/%s?auth=%s', $this->host, $endpoint, $this->password), [
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        $payload = $result->getContent();

        if ('GET' === $method && json_validate($payload)) {
            return json_decode($payload, true);
        }

        return [];
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
                static fn (array $contentAsBase64): ContentInterface => \in_array($contentAsBase64['type'], [File::class, Image::class, Audio::class], true)
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
