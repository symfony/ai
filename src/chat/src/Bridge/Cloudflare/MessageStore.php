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

use Symfony\AI\Chat\Exception\RuntimeException;
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
        private readonly string $namespace,
        #[\SensitiveParameter] private readonly string $accountId,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageBagNormalizer(new MessageNormalizer()),
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

        $this->createNamespace();
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

    public function save(MessageBag $messages, ?string $identifier = null): void
    {
        if (null !== $identifier && [] === $this->retrieveCurrentNamespace($identifier)) {
            $this->createNamespace($identifier);
        }

        $currentNamespace = $this->retrieveCurrentNamespace($identifier);

        $this->request('PUT', \sprintf('storage/kv/namespaces/%s/bulk', $currentNamespace['id']), [
            [
                'key' => $messages->getId()->toRfc4122(),
                'value' => $this->serializer->serialize($messages, 'json'),
            ],
        ]);
    }

    public function load(?string $identifier = null): MessageBag
    {
        if (null !== $identifier && [] === $this->retrieveCurrentNamespace($identifier)) {
            $this->createNamespace($identifier);
        }

        $currentNamespace = $this->retrieveCurrentNamespace($identifier);

        $keys = $this->request('GET', \sprintf('storage/kv/namespaces/%s/keys', $currentNamespace['id']));

        if ([] === $keys['result']) {
            return new MessageBag();
        }

        $messages = $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/get', $currentNamespace['id']), [
            'keys' => array_map(
                static fn (array $payload): string => $payload['name'],
                $keys['result'],
            ),
        ]);

        if (1 < \count($messages['result']['values'])) {
            throw new RuntimeException(\sprintf('More than one bag found for namespace "%s".', $identifier ?? $this->namespace));
        }

        return $this->serializer->deserialize(array_values($messages['result']['values'])[0] ?? [], MessageBag::class, 'json');
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
    private function retrieveCurrentNamespace(?string $identifier = null, ?int $page = 1): array
    {
        $namespaces = $this->request('GET', 1 === $page ? 'storage/kv/namespaces' : \sprintf('storage/kv/namespaces?page=%d', $page));

        if (0 === $namespaces['result_info']['total_count']) {
            return [];
        }

        $finalIdentifier = $identifier ?? $this->namespace;

        $filteredNamespaces = array_filter(
            $namespaces['result'],
            fn (array $payload): bool => $payload['title'] === $finalIdentifier,
        );

        if (0 === \count($filteredNamespaces) && $page !== $namespaces['result_info']['total_pages']) {
            return $this->retrieveCurrentNamespace(page: $namespaces['result_info']['page'] + 1);
        }

        if (0 === \count($filteredNamespaces) && $page === $namespaces['result_info']['total_pages']) {
            return [];
        }

        reset($filteredNamespaces);

        return array_values($filteredNamespaces)[0];
    }

    private function createNamespace(?string $identifier = null): void
    {
        $this->request('POST', 'storage/kv/namespaces', [
            'title' => $identifier ?? $this->namespace,
        ]);
    }
}
