<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\Cloudflare;

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
        private readonly string $namespace,
        #[\SensitiveParameter] private readonly string $accountId,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
        private readonly string $endpointUrl = 'https://api.cloudflare.com/client/v4/accounts',
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $namespaces = $this->request('GET', 'storage/kv/namespaces');

        $filteredNamespaces = array_filter(
            $namespaces['result'],
            fn (array $payload): bool => $payload['title'] === $this->namespace,
        );

        if (0 !== \count($filteredNamespaces)) {
            return;
        }

        $this->request('POST', 'storage/kv/namespaces', [
            'title' => $this->namespace,
        ]);
    }

    public function drop(): void
    {
        $currentNamespaceUuid = $this->retrieveCurrentNamespaceUuid();

        $keys = $this->request('GET', \sprintf('storage/kv/namespaces/%s/keys', $currentNamespaceUuid));

        if ([] === $keys['result']) {
            return;
        }

        $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/delete', $currentNamespaceUuid), array_map(
            static fn (array $payload): string => $payload['name'],
            $keys['result'],
        ));
    }

    public function save(MessageBag $messages): void
    {
        $currentNamespaceUuid = $this->retrieveCurrentNamespaceUuid();

        $this->request('PUT', \sprintf('storage/kv/namespaces/%s/bulk', $currentNamespaceUuid), array_map(
            fn (MessageInterface $message): array => [
                'key' => $message->getId()->toRfc4122(),
                'value' => $this->serializer->serialize($message, 'json'),
            ],
            $messages->getMessages(),
        ));
    }

    public function load(): MessageBag
    {
        $currentNamespaceUuid = $this->retrieveCurrentNamespaceUuid();

        $keys = $this->request('GET', \sprintf('storage/kv/namespaces/%s/keys', $currentNamespaceUuid));

        $messages = $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/get', $currentNamespaceUuid), [
            'keys' => array_map(
                static fn (array $payload): string => $payload['name'],
                $keys['result'],
            ),
        ]);

        return new MessageBag(...array_map(
            fn (string $message): MessageInterface => $this->serializer->deserialize($message, MessageInterface::class, 'json'),
            $messages['result']['values'],
        ));
    }

    /**
     * @param array<string, mixed>|list<array<string, string>> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $finalOptions = [
            'auth_bearer' => $this->apiKey,
        ];

        if ([] !== $payload) {
            $finalOptions['json'] = $payload;
        }

        $response = $this->httpClient->request($method, \sprintf('%s/%s/%s', $this->endpointUrl, $this->accountId, $endpoint), $finalOptions);

        return $response->toArray();
    }

    private function retrieveCurrentNamespaceUuid(): string
    {
        $namespaces = $this->request('GET', 'storage/kv/namespaces');

        $filteredNamespaces = array_filter(
            $namespaces['result'],
            fn (array $payload): bool => $payload['title'] === $this->namespace,
        );

        if (0 === \count($filteredNamespaces)) {
            throw new InvalidArgumentException('No namespace found.');
        }

        reset($filteredNamespaces);

        return $filteredNamespaces[0]['id'];
    }
}
