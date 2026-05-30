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
use Symfony\AI\Platform\ModelCatalog\ModelCard;
use Symfony\AI\Platform\ModelCatalog\ModelPricing;

final class ModelCardTest extends TestCase
{
    public function testTypedGetters()
    {
        $pricing = new ModelPricing(5.0, 30.0);
        $card = new ModelCard($pricing, 'eu');

        $this->assertSame($pricing, $card->getPricing());
        $this->assertSame('eu', $card->getRegion());
    }

    public function testDefaultsAreEmpty()
    {
        $card = new ModelCard();

        $this->assertNull($card->getPricing());
        $this->assertNull($card->getRegion());
        $this->assertSame([], $card->all());
    }

    public function testGetReadsExtraWithDefaultFallback()
    {
        $card = new ModelCard(null, null, ['tier' => 'flagship']);

        $this->assertSame('flagship', $card->get('tier'));
        $this->assertNull($card->get('missing'));
        $this->assertSame('fallback', $card->get('missing', 'fallback'));
    }

    public function testAllReturnsExtraBag()
    {
        $extra = ['tier' => 'flagship', 'scores' => ['speed' => 5]];
        $card = new ModelCard(null, null, $extra);

        $this->assertSame($extra, $card->all());
    }

    public function testFromArrayBuildsNestedPricing()
    {
        $card = ModelCard::fromArray([
            'pricing' => ['input' => 5.0, 'output' => 30.0, 'cachedInput' => 0.5],
            'region' => 'us',
            'extra' => ['tier' => 'flagship'],
        ]);

        $this->assertInstanceOf(ModelPricing::class, $card->getPricing());
        $this->assertSame(5.0, $card->getPricing()->getInputPerMillion());
        $this->assertSame(0.5, $card->getPricing()->getCachedInputPerMillion());
        $this->assertSame('us', $card->getRegion());
        $this->assertSame('flagship', $card->get('tier'));
    }

    public function testFromArrayWithAbsentKeys()
    {
        $card = ModelCard::fromArray([]);

        $this->assertNull($card->getPricing());
        $this->assertNull($card->getRegion());
        $this->assertSame([], $card->all());
    }
}
