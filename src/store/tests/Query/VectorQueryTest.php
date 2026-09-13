<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Query;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\AI\Platform\Vector\VectorInterface;
use Symfony\AI\Store\Query\HybridQuery;
use Symfony\AI\Store\Query\VectorQuery;

final class VectorQueryTest extends TestCase
{
    public function testCarriesTheVectorItWasBuiltWith()
    {
        $vector = new Vector([0.1, 0.2]);

        $this->assertSame($vector, (new VectorQuery($vector))->getVector());
    }

    public function testAcceptsAnyVectorImplementation()
    {
        // A store hands back documents typed on VectorInterface. Without this, feeding the vector
        // of such a document back into a query - a "more like this" search - needs a cast.
        $vector = new CustomVector([0.1, 0.2]);

        $this->assertSame($vector, (new VectorQuery($vector))->getVector());
    }

    public function testHybridQueryAcceptsAnyVectorImplementation()
    {
        $vector = new CustomVector([0.1, 0.2]);
        $query = new HybridQuery($vector, ['reality', 'tv'], 0.7);

        $this->assertSame($vector, $query->getVector());
        $this->assertSame(['reality', 'tv'], $query->getTexts());
    }
}

final class CustomVector implements VectorInterface
{
    /**
     * @param list<float> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDimensions(): int
    {
        return \count($this->data);
    }
}
