<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Client;

use Http\Discovery\Exception\NotFoundException;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Client\Transport\StdioTransport;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\McpBundle\Exception\LogicException;
use Symfony\AI\McpBundle\Exception\RuntimeException;

/**
 * Builds the SDK client transports from configuration.
 *
 * Three things cannot be decided when the container is compiled: an empty `env` must reach
 * `proc_open()` as `null` (an empty environment strips `PATH`, and `npx` then fails to start), the
 * inherited environment is only known at runtime, and the SDK's buffer sizes are non-nullable, so an
 * unset option has to select the shorter constructor call rather than pass `null`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class TransportFactory
{
    /**
     * @param list<string>          $command the program followed by its arguments
     * @param array<string, string> $env
     */
    public static function stdio(array $command, ?string $cwd, array $env, bool $inheritEnv, ?int $maxBufferSize, ?LoggerInterface $logger): StdioTransport
    {
        $program = array_shift($command);
        if (null === $program) {
            throw new LogicException('An MCP client server configured with the "stdio" transport needs a "command".');
        }

        $environment = null;
        if ([] !== $env) {
            $environment = $inheritEnv ? [...getenv(), ...$env] : $env;
        }

        if (null === $maxBufferSize) {
            return new StdioTransport($program, $command, $cwd, $environment, $logger);
        }

        return new StdioTransport($program, $command, $cwd, $environment, $logger, $maxBufferSize);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function http(
        string $endpoint,
        array $headers,
        ?ClientInterface $httpClient,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger,
        ?int $maxSseBufferBytes,
    ): HttpTransport {
        try {
            if (null === $maxSseBufferBytes) {
                return new HttpTransport($endpoint, $headers, $httpClient, $requestFactory, $streamFactory, $logger);
            }

            return new HttpTransport($endpoint, $headers, $httpClient, $requestFactory, $streamFactory, $logger, $maxSseBufferBytes);
        } catch (NotFoundException $e) {
            // Without a PSR-18 client the SDK falls back to discovery, whose failure message says
            // nothing about MCP.
            throw new RuntimeException(\sprintf('No PSR-18 HTTP client is available to reach the MCP server at "%s". Run "composer require symfony/http-client" or point the "http_client" option at your own PSR-18 service.', $endpoint), 0, $e);
        }
    }
}
