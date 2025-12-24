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
        $currentNamespace = $this->retrieveCurrentNamespace();

        if ([] !== $currentNamespace) {
            return;
        }

        $this->request('POST', 'storage/kv/namespaces', [
            'title' => $this->namespace,
        ]);
    }

    public function drop(): void
    {
        $currentNamespace = $this->retrieveCurrentNamespace();

        if ([] === $currentNamespace) {
            return;
        }

        $keys = $this->request('GET', \sprintf('storage/kv/namespaces/%s/keys', $currentNamespace['id']));

        if ([] === $keys['result']) {
            return;
        }

        $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/delete', $currentNamespace['id']), array_map(
            static fn (array $payload): string => $payload['name'],
            $keys['result'],
        ));
    }

    public function save(MessageBag $messages): void
    {
        $currentNamespace = $this->retrieveCurrentNamespace();

        $this->request('PUT', \sprintf('storage/kv/namespaces/%s/bulk', $currentNamespace['id']), array_map(
            fn (MessageInterface $message): array => [
                'key' => $message->getId()->toRfc4122(),
                'value' => $this->serializer->serialize($message, 'json'),
            ],
            $messages->getMessages(),
        ));
    }

    public function load(): MessageBag
    {
        $currentNamespace = $this->retrieveCurrentNamespace();

        $keys = $this->request('GET', \sprintf('storage/kv/namespaces/%s/keys', $currentNamespace['id']));

        $messages = $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/get', $currentNamespace['id']), [
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

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     supports_url_encoding: bool,
     * }|array{}
     */
    private function retrieveCurrentNamespace(?int $page = 1): array
    {
        $namespaces = $this->request('GET', 1 === $page ? 'storage/kv/namespaces' : \sprintf('storage/kv/namespaces?page=%d', $page));

        if (0 === $namespaces['result_info']['total_count']) {
            return [];
        }

        $filteredNamespaces = array_filter(
            $namespaces['result'],
            fn (array $payload): bool => $payload['title'] === $this->namespace,
        );

        if (0 === \count($filteredNamespaces) && $page !== $namespaces['result_info']['total_pages']) {
            return $this->retrieveCurrentNamespace($namespaces['result_info']['page'] + 1);
        }

        if (0 === \count($filteredNamespaces) && $page === $namespaces['result_info']['total_pages']) {
            return [];
        }

        reset($filteredNamespaces);

        return $filteredNamespaces[0];
    }
}
