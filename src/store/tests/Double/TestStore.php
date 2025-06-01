<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Double;

use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;

final class TestStore implements StoreInterface
{
    /**
     * @var VectorDocument[]
     */
    public array $documents = [];

    public int $addCalls = 0;

    public function add(VectorDocument ...$documents): void
    {
        ++$this->addCalls;
        $this->documents = array_merge($this->documents, $documents);
    }
}
