<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Bridge\Local;

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\ManagedStoreInterface;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
class InMemoryStore implements ManagedStoreInterface, StoreInterface
{
    /**
     * @var VectorDocument[]
     */
    private array $documents = [];

    public function __construct(
        private readonly DistanceCalculator $distanceCalculator = new DistanceCalculator(),
    ) {
    }

    public function setup(array $options = []): void
    {
        if ([] !== $options) {
            throw new InvalidArgumentException('No supported options.');
        }

        $this->drop();
    }

    public function add(VectorDocument ...$documents): void
    {
        array_push($this->documents, ...$documents);
    }

    /**
     * @param array{
     *     maxItems?: positive-int
     * } $options If maxItems is provided, only the top N results will be returned
     */
    public function query(Vector $vector, array $options = []): array
    {
        return $this->distanceCalculator->calculate($this->documents, $vector, $options['maxItems'] ?? null);
    }

    public function drop(): void
    {
        $this->documents = [];
    }
}
