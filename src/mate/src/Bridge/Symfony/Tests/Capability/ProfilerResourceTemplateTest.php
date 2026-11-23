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
use Symfony\AI\Mate\Attribute\MateResourceTemplate;
use Symfony\AI\Mate\Bridge\Symfony\Capability\ProfilerResourceTemplate;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorRegistry;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Bridge\Symfony\Tests\AssertsOutputSchemaTrait;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\MemoryDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\DataCollector\RouterDataCollector;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerResourceTemplateTest extends TestCase
{
    use AssertsOutputSchemaTrait;

    private ProfilerResourceTemplate $template;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__.'/../Fixtures/profiler';
        $registry = new CollectorRegistry([]);
        $provider = new ProfilerDataProvider($this->fixtureDir, $registry);

        $this->template = new ProfilerResourceTemplate($provider);
    }

    public function testGetProfileResourceReturnsValidStructure()
    {
        $resource = $this->template->getProfileResource('abc123');

        $this->assertArrayHasKey('uri', $resource);
        $this->assertArrayHasKey('mimeType', $resource);
        $this->assertArrayHasKey('text', $resource);
        $this->assertSame('symfony-profiler://profile/abc123', $resource['uri']);
        $this->assertSame('text/plain', $resource['mimeType']);
    }

    public function testGetProfileResourceIncludesMetadata()
    {
        $resource = $this->template->getProfileResource('abc123');

        $data = $this->decodeUntrusted($resource['text']);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('method', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('status_code', $data);
        $this->assertArrayHasKey('collectors', $data);

        $this->assertSame('abc123', $data['token']);
    }

    public function testGetProfileResourceIncludesCollectorUris()
    {
        $resource = $this->template->getProfileResource('abc123');

        $data = $this->decodeUntrusted($resource['text']);

        $this->assertArrayHasKey('collectors', $data);
        $this->assertIsArray($data['collectors']);
        $this->assertNotEmpty($data['collectors']);

        // Check collector structure
        foreach ($data['collectors'] as $collector) {
            $this->assertArrayHasKey('name', $collector);
            $this->assertArrayHasKey('uri', $collector);
            $this->assertStringStartsWith('symfony-profiler://profile/abc123/', $collector['uri']);
        }
    }

    public function testGetProfileResourceForNonExistentProfileReturnsError()
    {
        $resource = $this->template->getProfileResource('nonexistent');

        $data = Toon::decode($resource['text']);
        $this->assertIsArray($data);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Profile not found', $data['error']);
    }

    public function testGetCollectorResourceReturnsValidStructure()
    {
        $resource = $this->template->getCollectorResource('abc123', 'request');

        $this->assertArrayHasKey('uri', $resource);
        $this->assertArrayHasKey('mimeType', $resource);
        $this->assertArrayHasKey('text', $resource);
        $this->assertSame('symfony-profiler://profile/abc123/request', $resource['uri']);
    }

    public function testGetCollectorResourceReturnsToonContent()
    {
        $resource = $this->template->getCollectorResource('abc123', 'request');

        $data = $this->decodeUntrusted($resource['text']);

        $this->assertArrayHasKey('name', $data);
        $this->assertSame('request', $data['name']);
    }

    public function testResourcesReturnNonEmptyText()
    {
        $resources = [
            $this->template->getProfileResource('abc123'),
            $this->template->getCollectorResource('abc123', 'request'),
        ];

        foreach ($resources as $resource) {
            $this->assertNotEmpty($resource['text']);
            $this->assertIsString($resource['text']);
        }
    }

    public function testResourcesHandleErrorsGracefully()
    {
        $resource = $this->template->getCollectorResource('nonexistent', 'request');

        $this->assertArrayHasKey('text', $resource);
        $data = Toon::decode($resource['text']);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testResourcesReturnAvailabilityErrorWhenProfilerSupportIsUnavailable()
    {
        $template = new ProfilerResourceTemplate();

        $resource = $template->getCollectorResource('abc123', 'request');

        $this->assertSame('symfony-profiler://profile/abc123/request', $resource['uri']);
        $data = Toon::decode($resource['text']);
        $this->assertIsArray($data);
        $this->assertSame('Symfony profiler resources are not available in this Mate workspace.', $data['error']);
    }

    public function testProfileResourceMatchesOutputSchema()
    {
        $resource = $this->template->getProfileResource('abc123');

        $this->assertMatchesOutputSchema(ProfilerResourceTemplate::PROFILE_OUTPUT_SCHEMA, $this->decodeUntrusted($resource['text']));
    }

    public function testProfileResourceWithContextAndParentMatchesOutputSchema()
    {
        $dir = sys_get_temp_dir().'/mate_profile_resource_'.uniqid();
        mkdir($dir);

        try {
            $parent = new Profile('parent1');
            $profile = new Profile('child01');
            $profile->setParent($parent);
            foreach ([$parent, $profile] as $p) {
                $p->setMethod('GET');
                $p->setUrl('http://localhost/_fragment');
                $p->setIp('127.0.0.1');
                $p->setStatusCode(200);
                $p->setTime(2000000000);
            }
            $storage = new FileProfilerStorage('file:'.$dir);
            $storage->write($parent);
            $storage->write($profile);

            $template = new ProfilerResourceTemplate(new ProfilerDataProvider(['website' => $dir], new CollectorRegistry([])));
            $data = $this->decodeUntrusted($template->getProfileResource('child01')['text']);

            $this->assertSame('website', $data['context']);
            $this->assertSame('symfony-profiler://profile/parent1', $data['parent_profile']);
            $this->assertMatchesOutputSchema(ProfilerResourceTemplate::PROFILE_OUTPUT_SCHEMA, $data);
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function testCollectorResourceMatchesOutputSchema()
    {
        $dir = sys_get_temp_dir().'/mate_collector_resource_'.uniqid();
        mkdir($dir);

        try {
            $request = Request::create('/page');
            $response = new Response();

            $requestCollector = new RequestDataCollector();
            $requestCollector->collect($request, $response);
            $requestCollector->lateCollect();
            $routerCollector = new RouterDataCollector();
            $routerCollector->collect($request, $response);
            $memoryCollector = new MemoryDataCollector();
            $memoryCollector->collect($request, $response);
            $exceptionCollector = new ExceptionDataCollector();
            $exceptionCollector->collect($request, $response, new \RuntimeException('Boom'));

            $profile = new Profile('schema1');
            $profile->setMethod('GET');
            $profile->setUrl('http://localhost/page');
            $profile->setIp('127.0.0.1');
            $profile->setStatusCode(200);
            $profile->setCollectors([$requestCollector, $routerCollector, $memoryCollector, $exceptionCollector]);
            (new FileProfilerStorage('file:'.$dir))->write($profile);

            $container = new ContainerBuilder();
            $container->setParameter('mate.root_dir', $dir);
            (new PhpFileLoader($container, new FileLocator()))->load(\dirname(__DIR__, 2).'/config/config.php');
            $container->setParameter('ai_mate_symfony.profiler_dir', $dir);
            $container->getDefinition(ProfilerResourceTemplate::class)->setPublic(true);
            $container->compile();

            $template = $container->get(ProfilerResourceTemplate::class);
            $this->assertInstanceOf(ProfilerResourceTemplate::class, $template);
            $schema = $this->collectorOutputSchema();

            foreach (['request', 'router', 'memory', 'exception'] as $name) {
                $data = $this->decodeUntrusted($template->getCollectorResource('schema1', $name)['text']);

                $this->assertSame($name, $data['name']);
                $this->assertMatchesOutputSchema($schema, $data, $name);
                $this->assertMatchesOutputSchema($this->branch($schema, $name), $data, $name);
            }
        } finally {
            $this->removeDirectory($dir);
        }
    }

    public function testCollectorOutputSchemaHasOneBranchPerRegisteredFormatter()
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator()))->load(\dirname(__DIR__, 2).'/config/config.php');

        $names = [];
        foreach (array_keys($container->findTaggedServiceIds('ai_mate.profiler_collector_formatter')) as $class) {
            $formatter = new $class();
            $this->assertInstanceOf(CollectorFormatterInterface::class, $formatter);
            $names[] = $formatter->getName();
        }

        $branches = $this->collectorOutputSchema()['oneOf'];
        $fallback = array_values(array_filter($branches, static fn (array $branch): bool => 'other' === $branch['title']));
        $branches = array_values(array_filter($branches, static fn (array $branch): bool => 'other' !== $branch['title']));
        $titles = array_column($branches, 'title');

        $this->assertCount(1, $fallback, 'The "other" branch covers collectors without a formatter.');
        $this->assertNotEmpty($names);
        sort($names);
        sort($titles);
        $this->assertSame($names, $titles);
        foreach ($branches as $branch) {
            $this->assertSame([$branch['title']], $branch['properties']['name']['enum']);
            $this->assertSame($branch['title'], $branch['properties']['data']['title']);
        }
    }

    public function testOtherBranchMatchesACollectorWithoutAFormatter()
    {
        $other = $this->branch($this->collectorOutputSchema(), 'other');

        $this->assertMatchesOutputSchema($other, ['name' => 'events', 'data' => ['raw' => ['called_listeners' => []]], 'summary' => []]);
        $this->assertMatchesOutputSchema($other, ['name' => 'twig', 'data' => ['raw' => 'O:21:"Twig\\Profiler\\Profile":0:{}'], 'summary' => []]);
    }

    private function removeDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectorOutputSchema(): array
    {
        $attributes = (new \ReflectionMethod(ProfilerResourceTemplate::class, 'getCollectorResource'))->getAttributes(MateResourceTemplate::class);
        $this->assertCount(1, $attributes);
        $schema = $attributes[0]->newInstance()->outputSchema;
        $this->assertIsArray($schema);

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function branch(array $schema, string $title): array
    {
        foreach ($schema['oneOf'] as $branch) {
            if ($title === $branch['title']) {
                return $branch;
            }
        }

        $this->fail(\sprintf('No schema branch titled "%s".', $title));
    }

    /**
     * Decodes the text of a resource that carries application data, asserts the
     * untrusted-data envelope is present, and returns the payload.
     *
     * @return array<string, mixed>
     */
    private function decodeUntrusted(string $text): array
    {
        $decoded = Toon::decode($text);

        $this->assertIsArray($decoded);
        $this->assertSame(ResponseEncoder::UNTRUSTED_NOTICE, $decoded['_security_notice']);

        return $decoded['untrusted_data'];
    }
}
