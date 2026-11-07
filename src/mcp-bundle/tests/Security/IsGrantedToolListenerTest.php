<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\IsGrantedToolListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedToolListenerTest extends TestCase
{
    private const CONTROLLER = 'mcp.server.default.controller::handle';

    public function testAddsTheAttributesOfTheCalledTools()
    {
        $event = $this->dispatch(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'delete_account']]);

        $this->assertSame(['ROLE_CONTROLLER', 'ROLE_ACCOUNTS', 'ROLE_ADMIN'], $this->grantedAttributes($event));
    }

    public function testAddsTheAttributesOfEveryToolOfABatch()
    {
        $event = $this->dispatch([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'delete_account']],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'list_accounts']],
        ]);

        $this->assertSame(['ROLE_CONTROLLER', 'ROLE_ACCOUNTS', 'ROLE_ADMIN', 'ROLE_ACCOUNTS', 'ROLE_USER'], $this->grantedAttributes($event));
    }

    /**
     * @param array<array-key, mixed>|string $body
     */
    #[DataProvider('provideRequestsLeftAsIs')]
    public function testLeavesOtherRequestsAsIs(array|string $body, string $method = 'POST', string $controller = self::CONTROLLER)
    {
        $event = $this->dispatch($body, $method, $controller);

        $this->assertSame(['ROLE_CONTROLLER'], $this->grantedAttributes($event));
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>|string, 1?: string, 2?: string}>
     */
    public static function provideRequestsLeftAsIs(): iterable
    {
        $call = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'delete_account']];

        yield 'another controller' => [$call, 'POST', 'mcp.server.other.controller::handle'];
        yield 'not a POST' => [$call, 'GET'];
        yield 'another method' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']];
        yield 'an unknown tool' => [['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'unknown']]];
        yield 'invalid JSON' => ['{"jsonrpc":'];
        yield 'not an object' => ['"tools/call"'];
    }

    /**
     * @param array<array-key, mixed>|string $body
     */
    private function dispatch(array|string $body, string $method = 'POST', string $controller = self::CONTROLLER): ControllerEvent
    {
        $request = Request::create('http://localhost/mcp', $method, content: \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR));
        $request->attributes->set('_controller', $controller);

        $event = new ControllerEvent($this->createStub(HttpKernelInterface::class), [new FakeMcpController(), 'handle'], $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = new IsGrantedToolListener([self::CONTROLLER => [
            'delete_account' => [AccountTools::class, 'deleteAccount'],
            'list_accounts' => [AccountTools::class, 'listAccounts'],
        ]]);
        $listener->onKernelController($event);

        return $event;
    }

    /**
     * @return list<mixed>
     */
    private function grantedAttributes(ControllerEvent $event): array
    {
        return array_map(static fn (IsGranted $attribute): mixed => $attribute->attribute, $event->getAttributes(IsGranted::class));
    }
}

class FakeMcpController
{
    #[IsGranted('ROLE_CONTROLLER')]
    public function handle(): void
    {
    }
}

#[IsGranted('ROLE_ACCOUNTS')]
class AccountTools
{
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAccount(): void
    {
    }

    #[IsGranted('ROLE_USER')]
    public function listAccounts(): void
    {
    }
}
