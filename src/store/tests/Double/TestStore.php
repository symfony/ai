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

use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Store\Document\VectorDocument;
use Symfony\AI\Store\StoreInterface;

final class TestStore implements StoreInterface
{
    /**
     * @var VectorDocument[]
     */
    public array $documents = [];

    public int $addCalls = 0;

    public function add(VectorDocument|array $documents): void
    {
        if ($documents instanceof VectorDocument) {
            $documents = [$documents];
        }

        ++$this->addCalls;
        $this->documents = array_merge($this->documents, $documents);
    }

    public function query(Vector $vector, array $options = []): iterable
    {
        return $this->documents;
    }
}
