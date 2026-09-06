<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;

/**
 * The fixture container this reads is not hand-written: it was produced by Symfony's own
 * XmlDumper (see the class docblock of ServiceArgumentResolver for why that matters). If
 * the dumper ever changes the shape of an `<argument>` node, these tests fail against the
 * real format rather than against an assumption about it.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ContainerProviderArgumentsTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/container-arguments/App_KernelDevDebugContainer.xml';

    public function testParsesAServiceReference()
    {
        $arguments = $this->argumentsOf('app.api_client');

        $this->assertSame('service', $arguments[0]['type']);
        $this->assertSame('http_client', $arguments[0]['value']);
        $this->assertFalse($arguments[0]['literal']);
    }

    /**
     * The messenger bus case: middleware arrives as `<argument type="iterator">` holding one
     * service reference per middleware.
     */
    public function testParsesACollectionOfServiceReferences()
    {
        $arguments = $this->argumentsOf('messenger.bus.default');

        $this->assertCount(1, $arguments);
        $this->assertSame('collection', $arguments[0]['type']);

        $this->assertSame([
            'messenger.middleware.add_bus_name_stamp_middleware',
            'messenger.middleware.reject_redelivered_message_middleware',
            'messenger.middleware.dispatch_after_current_bus',
            'doctrine.orm.messenger.middleware_factory.transaction',
            'messenger.middleware.send_message',
            'messenger.middleware.handle_message',
        ], array_column($arguments[0]['value'], 'value'));
    }

    public function testParsesPlainScalarsWithTheirOriginalTypes()
    {
        $arguments = $this->argumentsOf('app.api_client');

        $this->assertSame('sk-live-do-not-log-this', $arguments[1]['value']);
        $this->assertSame(30, $arguments[2]['value'], 'A numeric argument should not come back as a string.');
        $this->assertTrue($arguments[4]['value']);
        $this->assertNull($arguments[5]['value']);

        foreach ([1, 2, 4, 5] as $position) {
            $this->assertSame('scalar', $arguments[$position]['type']);
            $this->assertTrue($arguments[$position]['literal']);
        }
    }

    public function testParsesAPlainCollectionOfScalars()
    {
        $arguments = $this->argumentsOf('app.api_client');

        $this->assertSame('collection', $arguments[3]['type']);
        $this->assertSame(['first', 'second'], array_column($arguments[3]['value'], 'value'));
    }

    public function testKeepsTheKeysOfAKeyedCollection()
    {
        $arguments = $this->argumentsOf('app.storage');

        $this->assertSame(['host', 'password', 'port'], array_column($arguments[0]['value'], 'key'));
        $this->assertSame(['db.internal', 'hunter2', 5432], array_column($arguments[0]['value'], 'value'));
    }

    /**
     * A tagged iterator has no value of its own, only the tag it collects — which is wiring,
     * so it is marked non-literal and never redacted later.
     */
    public function testParsesATaggedIterator()
    {
        $arguments = $this->argumentsOf('app.storage');

        $this->assertSame('scalar', $arguments[1]['type']);
        $this->assertSame('all services tagged "app.plugin"', $arguments[1]['value']);
        $this->assertFalse($arguments[1]['literal']);
    }

    public function testAServiceWithoutArgumentsHasNone()
    {
        $this->assertSame([], $this->argumentsOf('app.plain'));
    }

    public function testNestedArgumentsAreNotCountedAtTheServiceLevel()
    {
        // The six middleware references are children of the iterator, not arguments of the
        // bus, and SimpleXML would happily conflate the two if the parser asked for them
        // recursively.
        $this->assertCount(1, $this->argumentsOf('messenger.bus.default'));
    }

    /**
     * @return list<array{key: string|null, type: string, value: mixed, literal: bool}>
     */
    private function argumentsOf(string $id): array
    {
        $services = (new ContainerProvider())->getContainer(self::FIXTURE)->getServices();

        $this->assertArrayHasKey($id, $services);

        return $services[$id]->getArguments();
    }
}
