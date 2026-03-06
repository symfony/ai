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
 * Writes instruction artifacts that are consumed by coding agents.
 *
 * @phpstan-import-type ExtensionData from ComposerExtensionDiscovery
 *
 * @phpstan-type MaterializationResult array{
 *     instructions_file_updated: bool,
 *     agents_file_updated: bool,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AgentInstructionsMaterializer
{
    public const AGENTS_START_MARKER = '<!-- BEGIN AI_MATE_INSTRUCTIONS -->';
    public const AGENTS_END_MARKER = '<!-- END AI_MATE_INSTRUCTIONS -->';

    public function __construct(
        private string $rootDir,
        private AgentInstructionsAggregator $aggregator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, ExtensionData> $extensions
     *
     * @return MaterializationResult
     */
    public function materializeForExtensions(array $extensions): array
    {
        $instructions = $this->aggregator->aggregate($extensions);
        if (null === $instructions) {
            $instructions = $this->getFallbackInstructions();
        }

        $instructionsFileUpdated = $this->writeInstructionsFile($instructions);
        $agentsFileUpdated = $this->writeAgentsFile($extensions);

        return [
            'instructions_file_updated' => $instructionsFileUpdated,
            'agents_file_updated' => $agentsFileUpdated,
        ];
    }

    /**
     * @return MaterializationResult
     */
    public function synchronizeFromCurrentInstructionsFile(): array
    {
        $agentsFileUpdated = $this->writeAgentsFile();

        return [
            'instructions_file_updated' => true,
            'agents_file_updated' => $agentsFileUpdated,
        ];
    }

    private function getInstructionsFilePath(): string
    {
        return $this->rootDir.'/mate/AGENT_INSTRUCTIONS.md';
    }

    private function getAgentsFilePath(): string
    {
        return $this->rootDir.'/AGENTS.md';
    }

    private function writeInstructionsFile(string $instructions): bool
    {
        $path = $this->getInstructionsFilePath();
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $written = @file_put_contents($path, $this->normalizeContent($instructions));
        if (false === $written) {
            $this->logger->warning('Failed to write AGENT_INSTRUCTIONS.md file', [
                'path' => $path,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param array<string, ExtensionData>|null $extensions
     */
    private function writeAgentsFile(?array $extensions = null): bool
    {
        $path = $this->getAgentsFilePath();
        $managedBlock = $this->buildManagedBlock($extensions);

        if (!file_exists($path)) {
            $written = @file_put_contents($path, $this->normalizeContent($managedBlock));
            if (false === $written) {
                $this->logger->warning('Failed to create AGENTS.md file', [
                    'path' => $path,
                ]);

                return false;
            }

            return true;
        }

        $content = @file_get_contents($path);
        if (false === $content) {
            $this->logger->warning('Failed to read AGENTS.md file', [
                'path' => $path,
            ]);

            return false;
        }

        $updatedContent = $this->replaceManagedBlock($content, $managedBlock);
        $written = @file_put_contents($path, $this->normalizeContent($updatedContent));
        if (false === $written) {
            $this->logger->warning('Failed to update AGENTS.md file', [
                'path' => $path,
            ]);

            return false;
        }

        return true;
    }

    private function replaceManagedBlock(string $content, string $managedBlock): string
    {
        $startPos = strpos($content, self::AGENTS_START_MARKER);
        $endPos = strpos($content, self::AGENTS_END_MARKER);

        if (false === $startPos || false === $endPos || $endPos < $startPos) {
            $trimmedContent = trim($content);
            if ('' === $trimmedContent) {
                return $managedBlock;
            }

            return $trimmedContent."\n\n".$managedBlock;
        }

        $endPos += \strlen(self::AGENTS_END_MARKER);

        $prefix = rtrim(substr($content, 0, $startPos));
        $suffix = ltrim(substr($content, $endPos));

        $newContent = $managedBlock;
        if ('' !== $prefix) {
            $newContent = $prefix."\n\n".$managedBlock;
        }

        if ('' !== $suffix) {
            $newContent .= "\n\n".$suffix;
        }

        return $newContent;
    }

    /**
     * @param array<string, ExtensionData>|null $extensions
     */
    private function buildManagedBlock(?array $extensions = null): string
    {
        return implode("\n", [
            self::AGENTS_START_MARKER,
            'AI Mate Summary:',
            '- Role: MCP-powered, project-aware coding guidance and tools.',
            '- Required action: Read and follow `mate/AGENT_INSTRUCTIONS.md` before taking any action in this project, and prefer MCP tools over raw CLI commands whenever possible.',
            '- Installed extensions: '.$this->buildInstalledExtensionsText($extensions),
            self::AGENTS_END_MARKER,
        ]);
    }

    /**
     * @param array<string, ExtensionData>|null $extensions
     */
    private function buildInstalledExtensionsText(?array $extensions): string
    {
        if (null === $extensions) {
            return 'See `mate/extensions.php`.';
        }

        $extensionNames = [];
        foreach (array_keys($extensions) as $packageName) {
            if ('_custom' === $packageName) {
                continue;
            }

            $extensionNames[] = $packageName;
        }

        if ([] === $extensionNames) {
            return 'Custom project tools only.';
        }

        sort($extensionNames);

        return implode(', ', $extensionNames).'.';
    }

    private function normalizeContent(string $content): string
    {
        return rtrim($content)."\n";
    }

    private function getFallbackInstructions(): string
    {
        return <<<'TEXT'
# AI Mate Agent Instructions

No extension-specific instructions are currently available.
Run `vendor/bin/mate discover` to refresh discovered extensions and instructions.
Prefer MCP tools over equivalent shell commands when possible.
TEXT;
    }
}
