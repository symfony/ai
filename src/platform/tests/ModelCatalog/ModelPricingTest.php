<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\ModelCatalog;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\ModelCatalog\ModelPricing;

final class ModelPricingTest extends TestCase
{
    public function testGettersRoundTrip()
    {
        $pricing = new ModelPricing(5.0, 30.0, 0.5, 6.25, 'EUR');

        $this->assertSame(5.0, $pricing->getInputPerMillion());
        $this->assertSame(30.0, $pricing->getOutputPerMillion());
        $this->assertSame(0.5, $pricing->getCachedInputPerMillion());
        $this->assertSame(6.25, $pricing->getCacheWritePerMillion());
        $this->assertSame('EUR', $pricing->getCurrency());
    }

    public function testCacheFieldsDefaultToNullAndCurrencyToUsd()
    {
        $pricing = new ModelPricing(5.0, 30.0);

        $this->assertNull($pricing->getCachedInputPerMillion());
        $this->assertNull($pricing->getCacheWritePerMillion());
        $this->assertSame('USD', $pricing->getCurrency());
    }

    public function testFromArrayWithFullInput()
    {
        $pricing = ModelPricing::fromArray([
            'input' => 5.0,
            'output' => 30.0,
            'cachedInput' => 0.5,
            'cacheWrite' => 6.25,
            'currency' => 'EUR',
        ]);

        $this->assertSame(5.0, $pricing->getInputPerMillion());
        $this->assertSame(30.0, $pricing->getOutputPerMillion());
        $this->assertSame(0.5, $pricing->getCachedInputPerMillion());
        $this->assertSame(6.25, $pricing->getCacheWritePerMillion());
        $this->assertSame('EUR', $pricing->getCurrency());
    }

    public function testFromArrayWithMinimalInput()
    {
        $pricing = ModelPricing::fromArray(['input' => 5.0, 'output' => 30.0]);

        $this->assertSame(5.0, $pricing->getInputPerMillion());
        $this->assertSame(30.0, $pricing->getOutputPerMillion());
        $this->assertNull($pricing->getCachedInputPerMillion());
        $this->assertNull($pricing->getCacheWritePerMillion());
        $this->assertSame('USD', $pricing->getCurrency());
    }

    public function testFromArrayCoercesIntegerRatesToFloat()
    {
        $pricing = ModelPricing::fromArray(['input' => 5, 'output' => 30]);

        $this->assertSame(5.0, $pricing->getInputPerMillion());
        $this->assertSame(30.0, $pricing->getOutputPerMillion());
    }
}
