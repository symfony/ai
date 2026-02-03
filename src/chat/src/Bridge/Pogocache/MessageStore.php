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
use Symfony\AI\Chat\MessageBagNormalizer;
use Symfony\AI\Chat\MessageNormalizer;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\MessageBag;
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
            new MessageBagNormalizer(new MessageNormalizer()),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->request('PUT', $this->key);
    }

    public function drop(): void
    {
        $this->request('DELETE', $this->key);
    }

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        $this->request('PUT', $identifier ?? $this->key, $this->serializer->normalize($messages));
    }

    public function load(?string $identifier = null): MessageBag
    {
        $messages = $this->request('GET', $identifier ?? $this->key);

        return $this->serializer->denormalize($messages, MessageBag::class);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $response = $this->httpClient->request($method, \sprintf('%s/%s?auth=%s', $this->host, $endpoint, $this->password), [
            'json' => [] !== $payload ? $payload : new \stdClass(),
        ]);

        $payload = $response->getContent();

        if ('GET' === $method && json_validate($payload)) {
            return json_decode($payload, true);
        }

        return [];
    }
}
