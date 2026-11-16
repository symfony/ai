<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\SurrealDb;

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Exception\RuntimeException;
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
 * Keeps each conversation in a record, created only if absent and updated only while its version is the one that was loaded.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements ManagedMessageStoreInterface, MessageStoreInterface
{
    private string $authenticationToken = '';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $endpointUrl,
        private readonly string $user,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $namespace,
        private readonly string $database,
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
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
        $this->query(\sprintf('DELETE type::table(%s);', $this->literal($this->table)));
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->query(\sprintf('DELETE %s;', $this->thing($id)));
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        $record = $this->find($id);

        if (null === $record) {
            return MessageBag::forId($id);
        }

        return $this->serializer->deserialize($record['messages'], MessageBag::class, 'json');
    }

    public function save(MessageBag $messages): void
    {
        $record = $this->find($messages->getId());
        $stored = null === $record ? (new MessageBag())->withId($messages->getId()) : $this->serializer->deserialize($record['messages'], MessageBag::class, 'json');

        ConflictDetector::assertContinues($stored, $messages);

        $thing = $this->thing($messages->getId());
        $content = $this->literal([
            'version' => ConflictDetector::version($messages),
            'messages' => $this->serializer->serialize($messages, 'json'),
        ]);

        if (null === $record) {
            if ('OK' !== $this->query(\sprintf('CREATE %s CONTENT %s;', $thing, $content), false)['status']) {
                throw ConflictException::forMessages($messages);
            }

            return;
        }

        $result = $this->query(\sprintf('UPDATE %s MERGE %s WHERE version = %s RETURN AFTER;', $thing, $content, $this->literal($record['version'])));

        if ([] === $result['result']) {
            throw ConflictException::forMessages($messages);
        }
    }

    /**
     * @return array{version: string, messages: string}|null
     */
    private function find(AbstractUid $id): ?array
    {
        return $this->query(\sprintf('SELECT version, messages FROM ONLY %s;', $this->thing($id)))['result'];
    }

    private function thing(AbstractUid $id): string
    {
        return \sprintf('type::thing(%s, %s)', $this->literal($this->table), $this->literal($id->toRfc4122()));
    }

    // JSON values are valid SurrealQL literals, so encoding them keeps values from being read as statements
    private function literal(mixed $value): string
    {
        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{status: string, result: mixed}
     */
    private function query(string $statement, bool $throwOnError = true): array
    {
        $this->authenticate();

        $result = $this->httpClient->request('POST', \sprintf('%s/sql', $this->endpointUrl), [
            'headers' => [
                'Accept' => 'application/json',
                'Surreal-NS' => $this->namespace,
                'Surreal-DB' => $this->database,
                'Authorization' => \sprintf('Bearer %s', $this->authenticationToken),
            ],
            'body' => $statement,
        ])->toArray()[0];

        if ($throwOnError && 'OK' !== $result['status']) {
            throw new RuntimeException(\sprintf('The SurrealDB query failed: "%s"', \is_string($result['result']) ? $result['result'] : 'unknown error'));
        }

        return $result;
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
