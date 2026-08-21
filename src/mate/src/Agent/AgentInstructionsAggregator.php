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
use Symfony\AI\Mate\Discovery\PathGuard;

/**
 * Aggregates agent instructions from all installed extensions.
 *
 * Each extension can provide an INSTRUCTIONS.md file with instructions for AI agents,
 * typically documenting which `mate` CLI tools to prefer over raw shell commands.
 *
 * The aggregated instructions are materialized into `mate/AGENT_INSTRUCTIONS.md` so coding
 * agents can read them and learn how to best use the available tools.
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

    /**
     * @param array<string, ExtensionData>|null $extensions
     */
    public function aggregate(?array $extensions = null): ?string
    {
        if (null === $extensions) {
            $extensions = $this->extensions;
        }

        $extensionInstructions = [];

        foreach ($extensions as $packageName => $data) {
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
            $sections[] = $this->deepenMarkdownHeadings($content);
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

        if (PathGuard::hasTraversal($instructionsPath)) {
            $this->logger->warning('Invalid instructions path (contains path traversal)', [
                'package' => $packageName,
                'path' => $instructionsPath,
            ]);

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

        if (PathGuard::hasTraversal($instructionsPath)) {
            $this->logger->warning('Invalid instructions path (contains path traversal)', [
                'source' => 'root project',
                'path' => $instructionsPath,
            ]);

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
            ## AI Mate Agent Instructions

            AI Mate provides specialized tools for PHP development through the `vendor/bin/mate` CLI.
            Discover them with `vendor/bin/mate tools:list`, inspect a tool's parameters with
            `vendor/bin/mate tools:inspect <tool>`, and run one with
            `vendor/bin/mate tools:call <tool> --<param>=<value>` (add `--format=json` for
            machine-readable output). The following extensions are installed and provide tools you
            should prefer over the equivalent raw shell commands.
            MD;
    }

    private function deepenMarkdownHeadings(string $content): string
    {
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            if (!preg_match('/^(#{1,6})(\s+.*)$/', $line, $matches)) {
                continue;
            }

            $level = \strlen($matches[1]);
            if ($level < 6) {
                ++$level;
            }

            $lines[$index] = str_repeat('#', $level).$matches[2];
        }

        return implode("\n", $lines);
    }
}
