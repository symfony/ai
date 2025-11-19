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
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $key = '_message_store_pogocache',
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
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
        $this->request('PUT', $this->key, array_map(
            fn (MessageInterface $message): array => $this->serializer->normalize($message),
            $messages->getMessages(),
        ));
    }

    public function load(): MessageBag
    {
        $messages = $this->request('GET', $this->key);

        return new MessageBag(...array_map(
            fn (array $message): MessageInterface => $this->serializer->denormalize($message, MessageInterface::class),
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
}
