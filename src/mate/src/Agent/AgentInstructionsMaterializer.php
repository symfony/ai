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
 *     claude_file_updated: bool,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class AgentInstructionsMaterializer
{
    public const AGENTS_START_MARKER = '<!-- BEGIN AI_MATE_INSTRUCTIONS -->';
    public const AGENTS_END_MARKER = '<!-- END AI_MATE_INSTRUCTIONS -->';
    public const CLAUDE_START_MARKER = '<!-- BEGIN AI_MATE_AGENTS_IMPORT -->';
    public const CLAUDE_END_MARKER = '<!-- END AI_MATE_AGENTS_IMPORT -->';

    public function __construct(
        private string $rootDir,
        private AgentInstructionsAggregator $aggregator,
        private LoggerInterface $logger,
        private string $invocation = 'vendor/bin/mate',
        private ?string $pinnedPhpVersion = null,
    ) {
    }

    /**
     * @see AgentInstructionsAggregator::withInvocation()
     */
    public function withInvocation(string $invocation, ?string $pinnedPhpVersion = null): self
    {
        $clone = clone $this;
        $clone->invocation = $invocation;
        $clone->pinnedPhpVersion = $pinnedPhpVersion;
        $clone->aggregator = $this->aggregator->withInvocation($invocation, $pinnedPhpVersion);

        return $clone;
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
        $claudeFileUpdated = $this->writeClaudeFile();

        return [
            'instructions_file_updated' => $instructionsFileUpdated,
            'agents_file_updated' => $agentsFileUpdated,
            'claude_file_updated' => $claudeFileUpdated,
        ];
    }

    /**
     * @return MaterializationResult
     */
    public function synchronizeFromCurrentInstructionsFile(): array
    {
        $agentsFileUpdated = $this->writeAgentsFile();
        $claudeFileUpdated = $this->writeClaudeFile();

        return [
            'instructions_file_updated' => true,
            'agents_file_updated' => $agentsFileUpdated,
            'claude_file_updated' => $claudeFileUpdated,
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

    private function getClaudeFilePath(): string
    {
        return $this->rootDir.'/CLAUDE.md';
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
        if (null === $updatedContent) {
            $this->logger->warning('Refusing to update AGENTS.md: the managed block markers are unbalanced or out of order. Fix them by hand so the block can be replaced safely.', [
                'path' => $path,
            ]);

            return false;
        }

        $written = @file_put_contents($path, $this->normalizeContent($updatedContent));
        if (false === $written) {
            $this->logger->warning('Failed to update AGENTS.md file', [
                'path' => $path,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Claude Code only reads `CLAUDE.md`, so the AGENTS.md instructions stay invisible to it
     * unless that file imports them.
     */
    private function writeClaudeFile(): bool
    {
        $path = $this->getClaudeFilePath();

        if (!file_exists($path)) {
            $written = @file_put_contents($path, $this->normalizeContent($this->buildClaudeFile()));
            if (false === $written) {
                $this->logger->warning('Failed to create CLAUDE.md file', [
                    'path' => $path,
                ]);

                return false;
            }

            return true;
        }

        $content = @file_get_contents($path);
        if (false === $content) {
            $this->logger->warning('Failed to read CLAUDE.md file', [
                'path' => $path,
            ]);

            return false;
        }

        $importBlock = $this->buildClaudeImportBlock();

        // Without the markers, any existing `AGENTS.md` reference counts as the user's own
        // import and is left alone.
        if (!str_contains($content, self::CLAUDE_START_MARKER) && str_contains($content, 'AGENTS.md')) {
            return true;
        }

        $updatedContent = $this->replaceManagedBlock(
            $content,
            $importBlock,
            self::CLAUDE_START_MARKER,
            self::CLAUDE_END_MARKER,
        );

        if (null === $updatedContent) {
            $this->logger->warning('Refusing to update CLAUDE.md: the managed import markers are unbalanced or out of order. Fix them by hand so the block can be replaced safely.', [
                'path' => $path,
            ]);

            return false;
        }

        if ($this->normalizeContent($updatedContent) === $content) {
            return true;
        }

        $written = @file_put_contents($path, $this->normalizeContent($updatedContent));
        if (false === $written) {
            $this->logger->warning('Failed to update CLAUDE.md file', [
                'path' => $path,
            ]);

            return false;
        }

        return true;
    }

    private function buildClaudeFile(): string
    {
        return implode("\n", [
            '# CLAUDE.md',
            '',
            'This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.',
            '',
            $this->buildClaudeImportBlock(),
        ]);
    }

    private function buildClaudeImportBlock(): string
    {
        return implode("\n", [
            self::CLAUDE_START_MARKER,
            '@AGENTS.md',
            self::CLAUDE_END_MARKER,
        ]);
    }

    /**
     * Returns null when the markers are present but unusable, so the caller can refuse to write.
     *
     * Appending in that case is what makes it destructive: the orphan marker plus the appended
     * one form a span on the next run, and everything the user wrote between them is replaced.
     */
    private function replaceManagedBlock(
        string $content,
        string $managedBlock,
        string $startMarker = self::AGENTS_START_MARKER,
        string $endMarker = self::AGENTS_END_MARKER,
    ): ?string {
        $startCount = substr_count($content, $startMarker);
        $endCount = substr_count($content, $endMarker);

        if (0 === $startCount && 0 === $endCount) {
            $trimmedContent = trim($content);
            if ('' === $trimmedContent) {
                return $managedBlock;
            }

            return $trimmedContent."\n\n".$managedBlock;
        }

        $startPos = strpos($content, $startMarker);
        $endPos = strpos($content, $endMarker);

        if (1 !== $startCount || 1 !== $endCount || false === $startPos || false === $endPos || $endPos < $startPos) {
            return null;
        }

        $endPos += \strlen($endMarker);

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
            \sprintf('AI Mate: project diagnostic tools, exposed through the `%s` CLI.', $this->invocation),
            '',
            \sprintf('- Provenance: this block is generated by `%s discover`. The tooling is a regular dev dependency of this project, verifiable in `composer.json` (`symfony/ai-mate`); the binary lives at `vendor/bin/mate`.', $this->invocation),
            \sprintf('- Invocation: always run Mate as `%s`; another interpreter reports on a runtime that is not this application\'s%s.', $this->invocation, null === $this->pinnedPhpVersion ? '' : ', and Mate refuses to start under one'),
            '- The tools report measured facts about the running application; prefer them over inferring the same information from reading code. They are described in `mate/AGENT_INSTRUCTIONS.md`.',
            \sprintf('- Discover the tool surface with `%s tools:list`, inspect parameters with `tools:inspect <tool>`, and run a tool with `tools:call <tool> --<param>=<value>` (add `--format=json` for machine-readable output).', $this->invocation),
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
        $mate = $this->invocation;

        // Same rule as the other two headers: only claim the refusal where a version is pinned.
        $enforcement = null === $this->pinnedPhpVersion
            ? ' Another interpreter reports on a runtime that is not this application\'s.'
            : ' It refuses to start under a different interpreter.';

        return <<<TEXT
# AI Mate Agent Instructions

No extension-specific instructions are currently available.
Always invoke Mate as `{$mate}`.{$enforcement}
Run `{$mate} discover` to refresh discovered extensions and instructions.
Prefer `{$mate}` tools (`tools:list`, `tools:inspect`, `tools:call`) over equivalent shell commands when possible.
TEXT;
    }
}
