<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Capability;

use HelgeSverre\Toon\Toon;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ServiceTool;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * `symfony-service-detail` end to end, over a container dumped by Symfony's own XmlDumper.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ServiceToolArgumentsTest extends TestCase
{
    private ServiceTool $tool;

    protected function setUp(): void
    {
        $this->tool = new ServiceTool(
            \dirname(__DIR__).'/Fixtures/container-arguments',
            new ContainerProvider(),
        );
    }

    /**
     * The case this whole change exists for.
     *
     * Asking which middleware a messenger bus runs used to be unanswerable through Mate:
     * `symfony-service-detail` returned id, class, tags and calls, and the middleware is in
     * none of those — it is a constructor argument, which the tool did not read. Finding
     * `doctrine_transaction` on the default bus meant leaving the tool and opening the
     * dumped XML by hand.
     */
    public function testTheMiddlewareOfAMessengerBusIsVisible()
    {
        $detail = $this->decodeUntrusted($this->tool->getServiceDetail('messenger.bus.default'));

        $this->assertCount(1, $detail['arguments']);
        $this->assertSame('middlewareHandlers', $detail['arguments'][0]['name']);
        $this->assertSame('collection', $detail['arguments'][0]['type']);

        $middleware = array_column($detail['arguments'][0]['value'], 'value');

        $this->assertContains(
            'doctrine.orm.messenger.middleware_factory.transaction',
            $middleware,
            'The doctrine transaction middleware has to be findable on the bus that runs it.',
        );
        $this->assertSame([
            'messenger.middleware.add_bus_name_stamp_middleware',
            'messenger.middleware.reject_redelivered_message_middleware',
            'messenger.middleware.dispatch_after_current_bus',
            'doctrine.orm.messenger.middleware_factory.transaction',
            'messenger.middleware.send_message',
            'messenger.middleware.handle_message',
        ], $middleware, 'Middleware order is the execution order, so it has to survive too.');
    }

    public function testConstructorArgumentsAreNamedAndTyped()
    {
        $arguments = $this->decodeUntrusted($this->tool->getServiceDetail('app.api_client'))['arguments'];

        $this->assertSame(
            ['httpClient', 'apiKey', 'timeoutSeconds', 'scopes', 'debug', 'region'],
            array_column($arguments, 'name'),
        );
        $this->assertSame(
            ['service', 'scalar', 'scalar', 'collection', 'scalar', 'scalar'],
            array_column($arguments, 'type'),
        );

        $this->assertSame('http_client', $arguments[0]['value']);
        $this->assertSame(30, $arguments[2]['value']);
        $this->assertSame(['first', 'second'], array_column($arguments[3]['value'], 'value'));
        $this->assertTrue($arguments[4]['value']);
        $this->assertNull($arguments[5]['value']);
    }

    public function testASecretShapedParameterIsRedacted()
    {
        $arguments = $this->decodeUntrusted($this->tool->getServiceDetail('app.api_client'))['arguments'];

        $this->assertSame('apiKey', $arguments[1]['name']);
        $this->assertSame('***REDACTED***', $arguments[1]['value']);

        $this->assertStringNotContainsString(
            'sk-live-do-not-log-this',
            $this->tool->getServiceDetail('app.api_client'),
            'The raw secret must not survive anywhere in the encoded response.',
        );
    }

    public function testAKeyedCollectionRedactsOnlyWhatItHasTo()
    {
        $arguments = $this->decodeUntrusted($this->tool->getServiceDetail('app.storage'))['arguments'];

        $this->assertSame('connectionOptions', $arguments[0]['name']);
        $this->assertSame(['host', 'password', 'port'], array_column($arguments[0]['value'], 'name'));
        $this->assertSame(['db.internal', '***REDACTED***', 5432], array_column($arguments[0]['value'], 'value'));

        $this->assertSame('plugins', $arguments[1]['name']);
        $this->assertSame('all services tagged "app.plugin"', $arguments[1]['value']);
    }

    /**
     * A service built by a factory service has no class in the dump to reflect, so nothing
     * can be named — and an unnamed scalar is treated as a secret.
     */
    public function testAnUnreflectableServiceStillHidesItsScalars()
    {
        $arguments = $this->decodeUntrusted($this->tool->getServiceDetail('app.factory_made'))['arguments'];

        $this->assertSame('#0', $arguments[0]['name']);
        $this->assertSame('***REDACTED***', $arguments[0]['value']);

        $this->assertSame('logger', $arguments[1]['value'], 'Wiring stays visible; only literals fail closed.');

        $this->assertStringNotContainsString('production-database-dsn', $this->tool->getServiceDetail('app.factory_made'));
    }

    public function testAMissingClassAlsoFailsClosed()
    {
        $arguments = $this->decodeUntrusted($this->tool->getServiceDetail('app.missing_class'))['arguments'];

        $this->assertSame('***REDACTED***', $arguments[0]['value']);
    }

    public function testAServiceWithoutArgumentsReportsAnEmptyList()
    {
        $detail = $this->decodeUntrusted($this->tool->getServiceDetail('app.plain'));

        $this->assertArrayHasKey('arguments', $detail);
        $this->assertSame([], $detail['arguments']);
    }

    /**
     * The list tool is unchanged on purpose: arguments belong to the detail view, and
     * resolving them for every service would mean reflecting the whole container.
     */
    public function testTheServiceListStillReturnsOnlyIdsAndClasses()
    {
        $services = $this->decodeUntrusted($this->tool->getServices('messenger'))['services'];

        $this->assertSame(
            ['messenger.bus.default' => 'Symfony\AI\Mate\Bridge\Symfony\Tests\Fixtures\Service\MessageBus'],
            $services,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeUntrusted(string $response): array
    {
        $decoded = Toon::decode($response);

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);

        return $decoded['untrusted_data'];
    }
}
