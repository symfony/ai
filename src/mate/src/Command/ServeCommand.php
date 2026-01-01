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
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Icon;
use Mcp\Server;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\App;
use Symfony\AI\Mate\Discovery\FilteredDiscoveryLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Starts the MCP server with stdio transport.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
#[AsCommand('serve', 'Starts the MCP server with stdio transport')]
class ServeCommand extends Command
{
    private string $cacheDir;
    private FilteredDiscoveryLoader $loader;

    public function __construct(
        private ContainerInterface $container,
        private LoggerInterface $logger,
    ) {
        parent::__construct(self::getDefaultName());

        $rootDir = $container->getParameter('mate.root_dir');
        \assert(\is_string($rootDir));

        $cacheDir = $container->getParameter('mate.cache_dir');
        \assert(\is_string($cacheDir));
        $this->cacheDir = $cacheDir;

        $extensions = $this->container->getParameter('mate.extensions') ?? [];
        \assert(\is_array($extensions));

        $disabledFeatures = $this->container->getParameter('mate.disabled_features') ?? [];
        \assert(\is_array($disabledFeatures));

        $this->loader = new FilteredDiscoveryLoader(
            basePath: $rootDir,
            extensions: $extensions,
            disabledFeatures: $disabledFeatures,
            discoverer: new Discoverer($logger),
            logger: $logger
        );
    }

    public static function getDefaultName(): string
    {
        return 'serve';
    }

    public static function getDefaultDescription(): string
    {
        return 'Starts the MCP server with stdio transport';
    }

    protected function configure(): void
    {
        $this->addOption('force-keep-alive', null, InputOption::VALUE_NONE, 'Force a restart of the server if it stops.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('force-keep-alive')) {
            $output->writeln('The option --force-keep-alive requires using the "bin/mate" file. Try running "./vendor/bin/mate serve --force-keep-alive"');

            return Command::INVALID;
        }

        $server = Server::builder()
            ->setProtocolVersion(
                ProtocolVersion::tryFrom(
                    $this->container->getParameter('mate.mcp_protocol_version') ?? ProtocolVersion::V2025_03_26->value
                ) ?? ProtocolVersion::V2025_03_26
            )
            ->setServerInfo(
                App::NAME,
                App::VERSION,
                'AI development assistant MCP server',
                $this->getIcon(),
                'https://symfony.com/doc/current/ai/components/mate.html',
            )
            ->setContainer($this->container)
            ->addLoader($this->loader)
            ->setSession(new FileSessionStore($this->cacheDir.'/sessions'))
            ->setLogger($this->logger)
            ->build();

        $pidFileName = \sprintf('%s/server_%d.pid', $this->cacheDir, getmypid());
        if (false === @file_put_contents($pidFileName, (string) getmypid())) {
            $this->logger->warning('Failed to create PID file', ['path' => $pidFileName]);
        }

        try {
            $server->run(new StdioTransport());
        } finally {
            if (file_exists($pidFileName)) {
                @unlink($pidFileName);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return Icon[]
     */
    private function getIcon(): array
    {
        $image = file_get_contents(__DIR__.'/../../resources/symfony.ico');

        if (false === $image) {
            return [];
        }

        return [
            new Icon('data:image/x-icon;base64,'.base64_encode($image), 'image/x-icon', ['any']),
        ];
    }
}
