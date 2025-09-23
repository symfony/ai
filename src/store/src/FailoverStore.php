<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\RuntimeException;

final readonly class FailoverStore implements ManagedStoreInterface, StoreInterface
{
    private \SplObjectStorage $failedStores;

    /**
     * @param ManagedStoreInterface|StoreInterface[] $stores
     */
    public function __construct(
        private iterable $stores,
        private ?LoggerInterface $logger = null,
    ) {
        $this->failedStores = new \SplObjectStorage();
    }

    public function setup(array $options = []): void
    {
        $this->do(static fn (ManagedStoreInterface $store) => $store->setup($options));
    }

    public function drop(): void
    {
        $this->do(static fn (ManagedStoreInterface $store) => $store->drop());
    }

    public function add(VectorDocument ...$documents): void
    {
        $this->do(static fn (StoreInterface $store) => $store->add(...$documents));
    }

    public function query(Vector $vector, array $options = []): array
    {
        return $this->do(static fn (StoreInterface $store): array => $store->query($vector, $options));
    }

    private function do(\Closure $func)
    {
        foreach ($this->stores as $store) {
            if ($this->failedStores->contains($store)) {
                continue;
            }

            try {
                return $func($store);
            } catch (\Throwable $e) {
                $this->failedStores->attach($store);

                $this->logger?->warning('Store {store} failed, an exception was thrown: {exception}', [
                    'store' => $store::class,
                    'exception' => $e,
                ]);

                continue;
            }
        }

        throw new RuntimeException('No store available.');
    }
}
