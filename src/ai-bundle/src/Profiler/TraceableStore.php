<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\AiBundle\Profiler;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 *
 * @phpstan-type StoreData array{
 *     method: string,
 *     documents?: VectorDocument|VectorDocument[],
 *     query?: QueryInterface,
 *     ids?: string[]|string,
 *     options?: array<string, mixed>,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableStore implements StoreInterface, ResetInterface
{
    /**
     * @var StoreData[]
     */
    public array $calls = [];

    public function __construct(
        private readonly StoreInterface $store,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function add(VectorDocument|array $documents): void
    {
        $this->calls[] = [
            'method' => 'add',
            'documents' => $documents,
            'called_at' => $this->clock->now(),
        ];

        $this->store->add($documents);
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        $this->calls[] = [
            'method' => 'query',
            'query' => $query,
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        return $this->store->query($query, $options);
    }

    public function remove(array|string $ids, array $options = []): void
    {
        $this->calls[] = [
            'method' => 'remove',
            'ids' => $ids,
            'options' => $options,
            'called_at' => $this->clock->now(),
        ];

        $this->store->remove($ids, $options);
    }

    public function supports(string $queryClass): bool
    {
        return $this->store->supports($queryClass);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
