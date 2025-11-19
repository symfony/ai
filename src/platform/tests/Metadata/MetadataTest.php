<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Metadata\Metadata;

final class MetadataTest extends TestCase
{
    public function testItCanBeCreatedEmpty()
    {
        $metadata = new Metadata();
        $this->assertCount(0, $metadata);
        $this->assertSame([], $metadata->all());
    }

    public function testItCanBeCreatedWithInitialData()
    {
        $metadata = new Metadata(['key' => 'value']);
        $this->assertCount(1, $metadata);
        $this->assertSame(['key' => 'value'], $metadata->all());
    }

    public function testItCanAddNewMetadata()
    {
        $metadata = new Metadata();
        $metadata->add('key', 'value');

        $this->assertTrue($metadata->has('key'));
        $this->assertSame('value', $metadata->get('key'));
    }

    public function testItCanCheckIfMetadataExists()
    {
        $metadata = new Metadata(['key' => 'value']);

        $this->assertTrue($metadata->has('key'));
        $this->assertFalse($metadata->has('nonexistent'));
    }

    public function testItCanGetMetadataWithDefault()
    {
        $metadata = new Metadata(['key' => 'value']);

        $this->assertSame('value', $metadata->get('key'));
        $this->assertSame('default', $metadata->get('nonexistent', 'default'));
        $this->assertNull($metadata->get('nonexistent'));
    }

    public function testItCanRemoveMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $this->assertTrue($metadata->has('key'));

        $metadata->remove('key');
        $this->assertFalse($metadata->has('key'));
    }

    public function testItCanSetEntireMetadataArray()
    {
        $metadata = new Metadata(['key1' => 'value1']);
        $metadata->set(['key2' => 'value2', 'key3' => 'value3']);

        $this->assertFalse($metadata->has('key1'));
        $this->assertTrue($metadata->has('key2'));
        $this->assertTrue($metadata->has('key3'));
        $this->assertSame(['key2' => 'value2', 'key3' => 'value3'], $metadata->all());
    }

    public function testItImplementsJsonSerializable()
    {
        $metadata = new Metadata(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $metadata->jsonSerialize());
    }

    public function testItImplementsArrayAccess()
    {
        $metadata = new Metadata(['key' => 'value']);

        $this->assertArrayHasKey('key', $metadata);
        $this->assertSame('value', $metadata['key']);

        $metadata['new'] = 'newValue';
        $this->assertSame('newValue', $metadata['new']);

        unset($metadata['key']);
        $this->assertArrayNotHasKey('key', $metadata);
    }

    public function testItImplementsIteratorAggregate()
    {
        $metadata = new Metadata(['key1' => 'value1', 'key2' => 'value2']);
        $result = iterator_to_array($metadata);

        $this->assertSame(['key1' => 'value1', 'key2' => 'value2'], $result);
    }

    public function testItImplementsCountable()
    {
        $metadata = new Metadata();
        $this->assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $this->assertCount(1, $metadata);

        $metadata->add('key2', 'value2');
        $this->assertCount(2, $metadata);

        $metadata->remove('key');
        $this->assertCount(1, $metadata);
    }
}
