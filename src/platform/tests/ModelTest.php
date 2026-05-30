<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCard;
use Symfony\AI\Platform\ModelCatalog\ModelPricing;

final class ModelTest extends TestCase
{
    public function testReturnsName()
    {
        $model = new Model('gpt-4');

        $this->assertSame('gpt-4', $model->getName());
    }

    public function testReturnsCapabilities()
    {
        $model = new Model('gpt-4', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $this->assertSame([Capability::INPUT_TEXT, Capability::OUTPUT_TEXT], $model->getCapabilities());
    }

    public function testChecksSupportForCapability()
    {
        $model = new Model('gpt-4', [Capability::INPUT_TEXT, Capability::OUTPUT_TEXT]);

        $this->assertTrue($model->supports(Capability::INPUT_TEXT));
        $this->assertTrue($model->supports(Capability::OUTPUT_TEXT));
        $this->assertFalse($model->supports(Capability::INPUT_IMAGE));
    }

    public function testReturnsEmptyCapabilitiesByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getCapabilities());
    }

    public function testReturnsOptions()
    {
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ];
        $model = new Model('gpt-4', [], $options);

        $this->assertSame($options, $model->getOptions());
    }

    public function testReturnsEmptyOptionsByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertSame([], $model->getOptions());
    }

    public function testCardIsNullByDefault()
    {
        $model = new Model('gpt-4');

        $this->assertNull($model->getCard());
    }

    public function testExposesProvidedCard()
    {
        $card = new ModelCard(new ModelPricing(5.0, 30.0), 'us', ['tier' => 'flagship']);
        $model = new Model('gpt-4', [], [], $card);

        $this->assertSame($card, $model->getCard());
        $this->assertSame('us', $model->getCard()->getRegion());
        $this->assertSame('flagship', $model->getCard()->get('tier'));
    }
}
