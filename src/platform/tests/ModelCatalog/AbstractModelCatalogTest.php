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

    private function createTestCatalog(): AbstractModelCatalog
    {
        return new class extends AbstractModelCatalog {
            public function __construct()
            {
                $this->models = [
                    'test-model' => [
                        'class' => Model::class,
                        'capabilities' => [Capability::INPUT_TEXT],
                    ],
                ];
            }
        };
    }
}
