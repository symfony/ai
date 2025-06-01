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

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface VectorStoreInterface extends StoreInterface
{
    /**
     * @param array<string, mixed> $options
     *
     * @return VectorDocument[]
     */
    public function query(Vector $vector, array $options = [], ?float $minScore = null): array;
}
