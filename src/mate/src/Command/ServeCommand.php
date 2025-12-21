<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Command;

use Mcp\Capability\Discovery\Discoverer;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerTypeDiscovery;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\AI\Mate\Discovery\ServiceDiscovery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Starts the MCP server with stdio transport.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ServeCommand extends Command
{
    private ComposerTypeDiscovery $discovery;

    public function __construct(
        private LoggerInterface $logger,
        private ContainerBuilder $container,
    ) {
        parent::__construct(self::getDefaultName());
        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));
        $this->discovery = new ComposerTypeDiscovery($rootDir, $logger);
    }

    public static function getDefaultName(): ?string
    {
        return 'serve';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootDir = $this->container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $cacheDir = $this->container->getParameter('mate.cache_dir');
        \assert(\is_string($cacheDir));

        $discovery = new Discoverer($this->logger);
        $bridges = $this->getBridgesToLoad();
        (new ServiceDiscovery())->registerServices($discovery, $this->container, $rootDir, $bridges);

        $disabledVendorFeatures = $this->container->getParameter('mate.disabled_features') ?? [];
        \assert(\is_array($disabledVendorFeatures));
        /* @var array<string, array<string, array{enabled: bool}>> $disabledVendorFeatures */

        $this->container->compile();

        $loader = new FilteredDiscoveryLoader(
            basePath: $rootDir,
            bridges: $bridges,
            disabledFeatures: $disabledVendorFeatures,
            discoverer: $discovery,
            logger: $this->logger
        );

        $server = Server::builder()
            ->setServerInfo('ai-mate', '0.1.0', 'AI development assistant MCP server')
            ->setContainer($this->container)
            ->addLoader($loader)
            ->setSession(new FileSessionStore($cacheDir.'/sessions'))
            ->setLogger($this->logger)
            ->build();

        $server->run(new StdioTransport());

        return Command::SUCCESS;
    }

    /**
     * @return array<string, array{dirs: string[], includes: string[]}>
     */
    private function getBridgesToLoad(): array
    {
        $rootDir = $this->container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $packageNames = $this->container->getParameter('mate.enabled_bridges');
        \assert(\is_array($packageNames));
        /** @var array<int, string> $packageNames */

        /** @var array<string, array{dirs: array<string>, includes: array<string>}> $bridges */
        $bridges = [];

        foreach ($this->discovery->discover($packageNames) as $packageName => $data) {
            $bridges[$packageName] = $data;
        }

        $bridges['_custom'] = $this->discovery->discoverRootProject();

        return $bridges;
    }
}
