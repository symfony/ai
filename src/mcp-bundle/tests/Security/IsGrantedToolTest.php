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

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\McpBundle\Attribute\AsMcpApp;
use Symfony\AI\McpBundle\Attribute\AsMcpAppTool;
use Symfony\AI\McpBundle\McpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class IsGrantedToolTest extends TestCase
{
    private IsGrantedToolKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = new IsGrantedToolKernel('test', false);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->kernel->getProjectDir());

        // ErrorHandler leaks an exception handler before Symfony 7.4.17 and 8.1.5
        $handler = set_exception_handler(null);
        restore_exception_handler();
        if (\is_array($handler) && $handler[0] instanceof ErrorHandler) {
            restore_exception_handler();
        }
    }

    public function testDeniedToolCallReachesTheFirewall()
    {
        $response = $this->callTool('admin_only', 'alice');

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testAnonymousToolCallGetsTheEntryPointChallenge()
    {
        $response = $this->callTool('admin_only');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('Basic', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function testClassLevelAttributeAppliesToTheToolsOfTheClass()
    {
        $this->assertSame(403, $this->callTool('reviewers_only', 'alice')->getStatusCode());
    }

    public function testAppClassAttributeAppliesToTheToolsOfTheApp()
    {
        $this->assertSame(403, $this->callTool('admin_dashboard', 'alice')->getStatusCode());
        $this->assertSame(403, $this->callTool('refresh_dashboard', 'alice')->getStatusCode());

        $response = $this->callTool('admin_dashboard', 'admin');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('dashboard', (string) $response->getContent());
    }

    public function testAppToolMethodAttributeAppliesToThatTool()
    {
        $this->assertSame(403, $this->callTool('set_unit', 'alice')->getStatusCode());

        $response = $this->callTool('weather', 'alice');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('sunny', (string) $response->getContent());
    }

    public function testGrantedToolIsCalled()
    {
        $response = $this->callTool('admin_only', 'admin');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('secret', (string) $response->getContent());
    }

    public function testToolWithoutAttributeIsCalled()
    {
        $response = $this->callTool('public_tool', 'alice');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('public', (string) $response->getContent());
    }

    private function callTool(string $name, ?string $user = null): Response
    {
        $sessionId = null;
        if (null !== $user) {
            $response = $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ]], $user);
            $this->assertSame(200, $response->getStatusCode());
            $sessionId = $response->headers->get('Mcp-Session-Id');
            $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $user, $sessionId);
        }

        return $this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => new \stdClass()]], $user, $sessionId);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function post(array $message, ?string $user, ?string $sessionId = null): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (null !== $user) {
            $server += ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $user];
        }
        if (null !== $sessionId) {
            $server['HTTP_MCP_SESSION_ID'] = $sessionId;
        }

        $request = Request::create('http://localhost/mcp', 'POST', server: $server, content: json_encode($message, \JSON_THROW_ON_ERROR));

        return $this->kernel->handle($request);
    }
}

class IsGrantedToolKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new McpBundle();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/mcp-bundle-is-granted-tool';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);

        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['users' => ['memory' => ['users' => [
                'alice' => ['password' => 'alice', 'roles' => ['ROLE_USER']],
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN', 'ROLE_REVIEWER']],
            ]]]],
            'firewalls' => ['main' => ['stateless' => true, 'http_basic' => null]],
        ]);

        $container->extension('mcp', [
            'servers' => ['default' => [
                'registry' => [AdminTools::class, ReviewerTools::class, AdminDashboardApp::class, WeatherApp::class],
                'http' => ['path' => '/mcp'],
            ]],
        ]);

        $container->services()
            ->set('logger', NullLogger::class)
            ->set(AdminTools::class)->autoconfigure()
            ->set(ReviewerTools::class)->autoconfigure()
            ->set(AdminDashboardApp::class)->autoconfigure()
            ->set(WeatherApp::class)->autoconfigure();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'mcp');
    }
}

class AdminTools
{
    #[McpTool(name: 'admin_only')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminOnly(): string
    {
        return 'secret';
    }

    #[McpTool(name: 'public_tool')]
    public function publicTool(): string
    {
        return 'public';
    }
}

#[IsGranted('ROLE_REVIEWER')]
class ReviewerTools
{
    #[McpTool(name: 'reviewers_only')]
    public function reviewersOnly(): string
    {
        return 'reviewed';
    }
}

#[AsMcpApp(uri: 'ui://admin-dashboard', name: 'admin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardApp
{
    public function __invoke(): string
    {
        return '<html></html>';
    }

    public function render(): string
    {
        return 'dashboard';
    }

    #[AsMcpAppTool(name: 'refresh_dashboard')]
    public function refresh(): string
    {
        return 'refreshed';
    }
}

#[AsMcpApp(uri: 'ui://weather', name: 'weather')]
class WeatherApp
{
    public function __invoke(): string
    {
        return '<html></html>';
    }

    public function render(): string
    {
        return 'sunny';
    }

    #[AsMcpAppTool(name: 'set_unit')]
    #[IsGranted('ROLE_ADMIN')]
    public function setUnit(): string
    {
        return 'celsius';
    }
}
