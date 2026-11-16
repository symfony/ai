<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Cloudflare;

use Symfony\AI\Agent\MessageStore\ConflictDetector;
use Symfony\AI\Agent\MessageStore\ManagedMessageStoreInterface;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Agent\Toolbox\Source\SourceCollectionNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Serializer\MessageBagNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Keeps each conversation under its own key; Workers KV has no conditional writes, so saving checks the stored messages first.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $namespace,
        #[\SensitiveParameter] private readonly string $accountId,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
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

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $currentNamespace = $this->retrieveCurrentNamespace();

        $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/delete', $currentNamespace['id']), [$id->toRfc4122()]);
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $currentNamespace = $this->retrieveCurrentNamespace();

        $values = $this->request('POST', \sprintf('storage/kv/namespaces/%s/bulk/get', $currentNamespace['id']), [
            'keys' => [$id->toRfc4122()],
        ]);

        $payload = $values['result']['values'][$id->toRfc4122()] ?? null;

        if (!\is_string($payload)) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($payload, MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        ConflictDetector::assertContinues($this->load($messages->getId()), $messages);

        $currentNamespace = $this->retrieveCurrentNamespace();

        $this->request('PUT', \sprintf('storage/kv/namespaces/%s/bulk', $currentNamespace['id']), [[
            'key' => $messages->getId()->toRfc4122(),
            'value' => $this->serializer->serialize($messages, 'json'),
        ]]);
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

        return array_values($filteredNamespaces)[0];
    }
}
