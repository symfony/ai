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
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

final class AbstractModelCatalogTest extends TestCase
{
    public function testGetModelWithoutQueryParameters()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model');

        $this->assertSame('test-model', $model->getName());
        $this->assertSame([], $model->getOptions());
    }

    public function testGetModelWithStringQueryParameter()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?param=value');

        $this->assertSame('test-model', $model->getName());
        $this->assertSame(['param' => 'value'], $model->getOptions());
    }

    public function testGetModelWithIntegerQueryParameter()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?max_tokens=500');

        $this->assertSame('test-model', $model->getName());
        $options = $model->getOptions();
        $this->assertArrayHasKey('max_tokens', $options);
        $this->assertIsInt($options['max_tokens']);
        $this->assertSame(500, $options['max_tokens']);
    }

    public function testGetModelWithBooleanQueryParameters()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?think=true&stream=false');

        $this->assertSame('test-model', $model->getName());
        $options = $model->getOptions();
        $this->assertArrayHasKey('think', $options);
        $this->assertIsBool($options['think']);
        $this->assertTrue($options['think']);
        $this->assertArrayHasKey('stream', $options);
        $this->assertIsBool($options['stream']);
        $this->assertFalse($options['stream']);
    }

    public function testGetModelWithMultipleQueryParameters()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?max_tokens=500&temperature=0.7&stream=true');

        $this->assertSame('test-model', $model->getName());
        $options = $model->getOptions();

        $this->assertArrayHasKey('max_tokens', $options);
        $this->assertIsInt($options['max_tokens']);
        $this->assertSame(500, $options['max_tokens']);

        $this->assertArrayHasKey('temperature', $options);
        $this->assertIsFloat($options['temperature']);
        $this->assertSame(0.7, $options['temperature']);

        $this->assertArrayHasKey('stream', $options);
        $this->assertIsBool($options['stream']);
        $this->assertTrue($options['stream']);
    }

    public function testGetModelWithNestedArrayQueryParameters()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?options[max_tokens]=500&options[temperature]=0.7&options[metadata][version]=1');

        $this->assertSame('test-model', $model->getName());
        $options = $model->getOptions();

        $this->assertIsArray($options['options']);
        $this->assertSame(500, $options['options']['max_tokens']);
        $this->assertIsInt($options['options']['max_tokens']);
        $this->assertSame(0.7, $options['options']['temperature']);
        $this->assertIsFloat($options['options']['temperature']);
        $this->assertIsArray($options['options']['metadata']);
        $this->assertSame(1, $options['options']['metadata']['version']);
        $this->assertIsInt($options['options']['metadata']['version']);
    }

    public function testGetModelWithEmptyModelNameThrowsException()
    {
        $catalog = $this->createTestCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model name cannot be empty.');

        /* @phpstan-ignore argument.type */
        $catalog->getModel('');
    }

    public function testGetModelWithOnlyQueryStringThrowsException()
    {
        $catalog = $this->createTestCatalog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model name cannot be empty.');

        $catalog->getModel('?max_tokens=500');
    }

    public function testNumericStringsAreConvertedRecursively()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?a[b][c]=123&a[b][d]=text&a[e]=456');

        $options = $model->getOptions();

        $this->assertIsArray($options['a']);
        $this->assertIsArray($options['a']['b']);
        $this->assertSame(123, $options['a']['b']['c']);
        $this->assertIsInt($options['a']['b']['c']);
        $this->assertSame('text', $options['a']['b']['d']);
        $this->assertIsString($options['a']['b']['d']);
        $this->assertSame(456, $options['a']['e']);
        $this->assertIsInt($options['a']['e']);
    }

    public function testBooleanStringsAreConvertedRecursively()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?a[b][c]=true&a[b][d]=text&a[e]=false');

        $options = $model->getOptions();

        $this->assertIsArray($options['a']);
        $this->assertIsArray($options['a']['b']);
        $this->assertIsBool($options['a']['b']['c']);
        $this->assertTrue($options['a']['b']['c']);
        $this->assertIsString($options['a']['b']['d']);
        $this->assertSame('text', $options['a']['b']['d']);
        $this->assertIsBool($options['a']['e']);
        $this->assertFalse($options['a']['e']);
    }

    public function testScientificNotationIsConvertedToFloat()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model?frequency_penalty=1e-5&presence_penalty=2.5E3');

        $options = $model->getOptions();

        $this->assertIsFloat($options['frequency_penalty']);
        $this->assertSame(1e-5, $options['frequency_penalty']);
        $this->assertIsFloat($options['presence_penalty']);
        $this->assertSame(2.5E3, $options['presence_penalty']);
    }

    public function testGetModelWithoutMetadataYieldsNullCard()
    {
        $catalog = $this->createTestCatalog();
        $model = $catalog->getModel('test-model');

        $this->assertNull($model->getCard());
    }

    public function testGetModelWithMetadataYieldsPopulatedCard()
    {
        $catalog = $this->createCatalog([
            'gpt-4' => [
                'class' => Model::class,
                'capabilities' => [Capability::INPUT_TEXT],
                'metadata' => [
                    'pricing' => ['input' => 5.0, 'output' => 30.0, 'cachedInput' => 0.5],
                    'region' => 'us',
                    'extra' => ['tier' => 'flagship'],
                ],
            ],
        ]);

        $card = $catalog->getModel('gpt-4')->getCard();

        $this->assertNotNull($card);
        $this->assertSame(5.0, $card->getPricing()->getInputPerMillion());
        $this->assertSame(0.5, $card->getPricing()->getCachedInputPerMillion());
        $this->assertSame('us', $card->getRegion());
        $this->assertSame('flagship', $card->get('tier'));
    }

    public function testMetadataIsParsedAlongsideQueryStringOptions()
    {
        $catalog = $this->createCatalog([
            'gpt-4' => [
                'class' => Model::class,
                'capabilities' => [Capability::INPUT_TEXT],
                'metadata' => ['region' => 'eu'],
            ],
        ]);

        $model = $catalog->getModel('gpt-4?temperature=0.7');

        $this->assertSame(['temperature' => 0.7], $model->getOptions());
        $this->assertSame('eu', $model->getCard()->getRegion());
    }

    public function testMetadataSurvivesThroughSubclassConstructor()
    {
        // Claude overrides __construct; guards the positional-arg trap where a subclass
        // that does not forward the 4th argument would silently drop the card.
        $catalog = $this->createCatalog([
            'claude-3-7-sonnet' => [
                'class' => Claude::class,
                'capabilities' => [Capability::INPUT_MESSAGES],
                'metadata' => ['region' => 'us', 'extra' => ['tier' => 'flagship']],
            ],
        ]);

        $model = $catalog->getModel('claude-3-7-sonnet');

        $this->assertInstanceOf(Claude::class, $model);
        $this->assertNotNull($model->getCard());
        $this->assertSame('us', $model->getCard()->getRegion());
        $this->assertSame('flagship', $model->getCard()->get('tier'));
    }

    private function createTestCatalog(): AbstractModelCatalog
    {
        return $this->createCatalog([
            'test-model' => [
                'class' => Model::class,
                'capabilities' => [Capability::INPUT_TEXT],
            ],
        ]);
    }

    /**
     * @param array<string, array{class: class-string, capabilities: list<Capability>, metadata?: array<string, mixed>}> $models
     */
    private function createCatalog(array $models): AbstractModelCatalog
    {
        return new class($models) extends AbstractModelCatalog {
            /**
             * @param array<string, array{class: class-string, capabilities: list<Capability>, metadata?: array<string, mixed>}> $models
             */
            public function __construct(array $models)
            {
                $this->models = $models;
            }
        };
    }
}
