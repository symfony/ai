<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Mock;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Mock\Factory;
use Symfony\AI\Platform\Mock\ModelCatalog;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

final class FactoryTest extends TestCase
{
    public function testCreatePlatformWithStringRespondsToAnyModel()
    {
        $platform = Factory::createPlatform('hi');

        $this->assertSame('hi', $platform->invoke('any-model', 'q')->asText());
    }

    public function testCreateProviderWithCatalogGatesRouting()
    {
        $provider = Factory::createProvider('answer', new ModelCatalog([
            'fake-model' => ['class' => Model::class, 'capabilities' => []],
        ]));

        $this->assertTrue($provider->supports('fake-model'));
        $this->assertFalse($provider->supports('unknown-model'));
    }

    public function testInvokeUnknownModelThrowsWithSpecificCatalog()
    {
        $platform = Factory::createPlatform('answer', new ModelCatalog([
            'fake-model' => ['class' => Model::class, 'capabilities' => []],
        ]));

        $this->expectException(ModelNotFoundException::class);

        $platform->invoke('unknown-model', 'q')->asText();
    }

    public function testStructuredResultRoundTrips()
    {
        $platform = Factory::createPlatform(static function (Model $model, array|string $payload, array $options): ResultInterface|string {
            if (isset($options['response_format'])) {
                return new ObjectResult((object) ['answer' => 42]);
            }

            return 'plain';
        });

        $result = $platform->invoke('any-model', 'q', ['response_format' => ['type' => 'json']]);

        $this->assertEquals((object) ['answer' => 42], $result->asObject());
    }

    public function testStreamResultRoundTrips()
    {
        $platform = Factory::createPlatform(static fn (): ResultInterface => new StreamResult(
            (static function (): \Generator {
                yield new TextDelta('Hel');
                yield new TextDelta('lo');
            })(),
        ));

        $deltas = [];
        foreach ($platform->invoke('any-model', 'q', ['stream' => true])->asStream() as $delta) {
            $deltas[] = (string) $delta;
        }

        $this->assertSame(['Hel', 'lo'], $deltas);
    }

    public function testVectorResultRoundTrips()
    {
        $platform = Factory::createPlatform(static fn (): ResultInterface => new VectorResult([new Vector([0.1, 0.2, 0.3])]));

        $vectors = $platform->invoke('any-model', 'q')->asVectors();

        $this->assertCount(1, $vectors);
        $this->assertSame([0.1, 0.2, 0.3], $vectors[0]->getData());
    }

    public function testMultiProviderRoutingPicksMockByModelName()
    {
        $providerA = Factory::createProvider('from A', new ModelCatalog([
            'model-a' => ['class' => Model::class, 'capabilities' => []],
        ]), name: 'fake-a');

        $providerB = Factory::createProvider('from B', new ModelCatalog([
            'model-b' => ['class' => Model::class, 'capabilities' => []],
        ]), name: 'fake-b');

        $platform = new Platform([$providerA, $providerB]);

        $this->assertSame('from A', $platform->invoke('model-a', 'q')->asText());
        $this->assertSame('from B', $platform->invoke('model-b', 'q')->asText());
    }
}
