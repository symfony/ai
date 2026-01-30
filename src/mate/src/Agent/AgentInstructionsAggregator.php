<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Agent;

use Psr\Log\LoggerInterface;
use Symfony\AI\Mate\Discovery\ComposerExtensionDiscovery;

/**
 * Aggregates agent instructions from all installed extensions.
 *
 * Each extension can provide an INSTRUCTIONS.md file with instructions for AI agents,
 * typically documenting CLI → MCP tool mappings, benefits, and usage modes.
 *
 * These instructions are injected via the MCP protocol's `instructions` field
 * during the server handshake, allowing agents to understand how to best use
 * the available MCP tools.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AgentInstructionsAggregator
{
    /**
     * @param array<string, ExtensionData> $extensions
     */
    public function __construct(
        private string $rootDir,
        private array $extensions,
        private LoggerInterface $logger,
    ) {
    }

    public function aggregate(): ?string
    {
        $extensionInstructions = [];

        foreach ($this->extensions as $packageName => $data) {
            if ('_custom' === $packageName) {
                $content = $this->loadRootProjectInstructions($data);
            } else {
                $content = $this->loadExtensionInstructions($packageName, $data);
            }

            if (null !== $content) {
                $extensionInstructions[$packageName] = $content;
            }
        }

        if ([] === $extensionInstructions) {
            return null;
        }

        $sections = [$this->getGlobalHeader()];
        foreach ($extensionInstructions as $content) {
            $sections[] = $content;
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * @param ExtensionData $data
     */
    private function loadExtensionInstructions(string $packageName, array $data): ?string
    {
        $instructionsPath = $data['instructions'] ?? null;

        if (null === $instructionsPath) {
            return null;
        }

        $fullPath = $this->rootDir.'/vendor/'.$packageName.'/'.ltrim($instructionsPath, '/');

        return $this->readInstructionsFile($fullPath, $packageName);
    }

    /**
     * @param ExtensionData $data
     */
    private function loadRootProjectInstructions(array $data): ?string
    {
        $instructionsPath = $data['instructions'] ?? null;

        if (null === $instructionsPath) {
            return null;
        }

        $fullPath = $this->rootDir.'/'.ltrim($instructionsPath, '/');

        return $this->readInstructionsFile($fullPath, 'root project');
    }

    private function readInstructionsFile(string $path, string $source): ?string
    {
        if (!file_exists($path)) {
            $this->logger->warning('Agent instructions file not found', [
                'source' => $source,
                'path' => $path,
            ]);

            return null;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            $this->logger->warning('Failed to read agent instructions file', [
                'source' => $source,
                'path' => $path,
                'error' => error_get_last()['message'] ?? 'Unknown error',
            ]);

            return null;
        }

        $content = trim($content);
        if ('' === $content) {
            $this->logger->debug('Empty agent instructions file', [
                'source' => $source,
                'path' => $path,
            ]);

            return null;
        }

        $this->logger->debug('Loaded agent instructions', [
            'source' => $source,
            'path' => $path,
            'length' => \strlen($content),
        ]);

        return $content;
    }

    private function getGlobalHeader(): string
    {
        return <<<'MD'
            # AI Mate Agent Instructions

            This MCP server provides specialized tools for PHP development.
            The following extensions are installed and provide MCP tools that you should
            prefer over running CLI commands directly.
            MD;
    }
}
