<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Chat\Bridge\SurrealDb;

use Symfony\AI\Chat\Exception\InvalidArgumentException;
use Symfony\AI\Chat\Exception\RuntimeException;
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
    private string $authenticationToken = '';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $namespace,
        private readonly string $database,
        private readonly SerializerInterface&NormalizerInterface&DenormalizerInterface $serializer = new Serializer([
            new ArrayDenormalizer(),
            new MessageNormalizer(),
        ], [new JsonEncoder()]),
        private readonly string $table = '_message_store_surrealdb',
        private readonly bool $isNamespacedUser = false,
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }
    }

    public function drop(): void
    {
        $this->request('DELETE', \sprintf('key/%s', $this->table));
    }

    public function save(MessageBag $messages): void
    {
        foreach ($messages->getMessages() as $message) {
            $this->request('POST', \sprintf('key/%s', $this->table), $this->serializer->normalize($message));
        }
    }

    public function load(): MessageBag
    {
        $messages = $this->request('GET', \sprintf('key/%s', $this->table), []);

        return new MessageBag(...array_map(
            fn (array $message): MessageInterface => $this->serializer->denormalize($message, MessageInterface::class),
            $messages[0]['result'],
        ));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string|int, mixed>
     */
    private function request(string $method, string $endpoint, array $payload = []): array
    {
        $this->authenticate();

        $finalPayload = [];

        if ([] !== $payload) {
            $finalPayload = [
                'json' => $payload,
            ];
        }

        $response = $this->httpClient->request($method, \sprintf('%s/%s', $this->endpointUrl, $endpoint), [
            ...$finalPayload,
            ...[
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Surreal-NS' => $this->namespace,
                    'Surreal-DB' => $this->database,
                    'Authorization' => \sprintf('Bearer %s', $this->authenticationToken),
                ],
            ],
        ]);

        return $response->toArray();
    }

    private function authenticate(): void
    {
        if ('' !== $this->authenticationToken) {
            return;
        }

        $authenticationPayload = [
            'user' => $this->user,
            'pass' => $this->password,
        ];

        if ($this->isNamespacedUser) {
            $authenticationPayload['ns'] = $this->namespace;
            $authenticationPayload['db'] = $this->database;
        }

        $authenticationResponse = $this->httpClient->request('POST', \sprintf('%s/signin', $this->endpointUrl), [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => $authenticationPayload,
        ]);

        $payload = $authenticationResponse->toArray();

        if (!\array_key_exists('token', $payload)) {
            throw new RuntimeException('The SurrealDB authentication response does not contain a token.');
        }

        $this->authenticationToken = $payload['token'];
    }
}
