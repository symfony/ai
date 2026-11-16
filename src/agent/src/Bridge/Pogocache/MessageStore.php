<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Bridge\Pogocache;

use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\MessageStore\ConflictDetector;
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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Keeps each conversation under its own key.
 *
 * New conversations are created only if absent, later saves check the stored messages first.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MessageStore implements MessageStoreInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $host,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $key = '_message_store_pogocache',
        private readonly SerializerInterface $serializer = new Serializer([
            new MessageBagNormalizer(),
            new SourceCollectionNormalizer(),
        ], [new JsonEncoder()]),
    ) {
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return $this->fetch($id) ?? MessageBag::forId($id);
    }

    public function save(MessageBag $messages): void
    {
        $stored = $this->fetch($messages->getId());

        ConflictDetector::assertContinues($stored ?? MessageBag::forId($messages->getId()), $messages);

        $response = $this->request('PUT', $this->conversationKey($messages->getId()), $this->serializer->serialize($messages, 'json'), null === $stored);

        if (404 === $response->getStatusCode()) {
            throw ConflictException::forMessages($messages);
        }

        $response->getContent();
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $response = $this->request('DELETE', $this->conversationKey($id));

        if (404 !== $response->getStatusCode()) {
            $response->getContent();
        }
    }

    private function fetch(AbstractUid&TimeBasedUidInterface $id): ?MessageBag
    {
        $response = $this->request('GET', $this->conversationKey($id));

        if (404 === $response->getStatusCode()) {
            return null;
        }

        return $this->serializer->deserialize($response->getContent(), MessageBag::class, 'json');
    }

    private function conversationKey(AbstractUid $id): string
    {
        return $this->key.':'.$id->toRfc4122();
    }

    private function request(string $method, string $key, ?string $body = null, bool $onlyIfAbsent = false): ResponseInterface
    {
        $query = ['auth' => $this->password];

        if ($onlyIfAbsent) {
            $query['nx'] = 1;
        }

        return $this->httpClient->request($method, \sprintf('%s/%s', $this->host, $key), [
            'query' => $query,
            'body' => $body ?? '',
        ]);
    }
}
