<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final readonly class RawHttpStreamResult implements RawResultInterface
{
    public function __construct(
        private \Generator $generator,
    ) {
    }

    public function getData(): array
    {
        return array_map(
            static fn (ChunkInterface $item): string => $item->getContent(),
            iterator_to_array($this->generator),
        );
    }

    public function getObject(): object
    {
        return $this->generator;
    }
}
