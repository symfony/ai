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
interface StoreInterface
{
    /**
     * @param VectorDocument|VectorDocument[] $documents
     */
    public function add(VectorDocument|array $documents): void;

    /**
     * @param VectorDocument|VectorDocument[] $documents
     * @param array<string, mixed>            $options
     */
    public function remove(VectorDocument|array $documents, array $options = []): void;

    /**
     * @param array<string, mixed> $options
     *
     * @return iterable<VectorDocument>
     */
    public function query(Vector $vector, array $options = []): iterable;
}
