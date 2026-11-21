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

use HelgeSverre\Toon\Toon;
use Symfony\AI\Mate\Command\Batch\PathResolver;
use Symfony\AI\Mate\Command\Batch\ReferenceResolver;
use Symfony\AI\Mate\Command\Trait\EnsuresToonFormatAvailabilityTrait;
use Symfony\AI\Mate\Command\Trait\RendersToolResultTrait;
use Symfony\AI\Mate\Discovery\CapabilityRegistry;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\ResourceNotFoundException;
use Symfony\AI\Mate\Exception\RuntimeException;
use Symfony\AI\Mate\Invocation\ResourceReader;
use Symfony\AI\Mate\Invocation\ToolInvoker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Execute several tools and resource reads in one command call, so an agent that needs
 * multiple results for a single investigation pays for one round-trip instead of N.
 *
 * Each call is dispatched sequentially: Mate tool handlers are plain synchronous PHP
 * calls, and building real concurrent dispatch is out of scope for this command. One
 * call failing (unknown tool or resource, thrown exception) does not abort the others; each result
 * is reported independently so partial results are still useful.
 *
 * A call may carry an `id` so later calls can use its result as `{{id.path}}` in their params or
 * resource URI, `output: false` to keep it out of the output and `pick` to trim its result.
 * References always resolve against the full result of the earlier call.
 *
 * @phpstan-type BatchCall array{id?: string, tool?: string, params?: array<string, mixed>, resource?: string, output?: bool, pick?: list<string>}
 * @phpstan-type BatchResult array{id?: string, tool?: string, resource?: string, ok: bool, result: mixed, error: string|null, missing_paths?: list<string>}
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsCommand('tools:call-batch', 'Execute several tools in one command call')]
class ToolsCallBatchCommand extends Command
{
    use EnsuresToonFormatAvailabilityTrait;
    use RendersToolResultTrait;

    public function __construct(
        private CapabilityRegistry $registry,
        private ToolInvoker $invoker,
        private ResourceReader $reader,
    ) {
        parent::__construct(self::getDefaultName());
    }

    public static function getDefaultName(): string
    {
        return 'tools:call-batch';
    }

    public static function getDefaultDescription(): string
    {
        return 'Execute several tools in one command call';
    }

    protected function configure(): void
    {
        $script = $_SERVER['PHP_SELF'] ?? 'vendor/bin/mate';

        $this
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'A JSON array of {"tool": "...", "params": {...}} or {"resource": "<uri>"} call objects. Optional per call: "id" (name for later references), "output": false (hide the entry), "pick": ["path", ...] (keep only these paths). Params and resource URIs may use "{{id.path}}" to read from an earlier call with an id, e.g. {{list.profiles[0].token}}, paths support a.b, [0], [*] and [:3]')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (json, pretty, toon)', 'pretty')
            ->setHelp(
                <<<HELP
The <info>%command.name%</info> command executes several tools and resource reads in one call, saving the
round-trips that calling <info>tools:call</info> or <info>resources:read</info> once per item would cost. Calls run sequentially and one
failing call does not abort the others.

<info>Usage Examples:</info>

  <comment># Run two tools and correlate their results</comment>
  %command.full_name% --json='[{"tool": "symfony-profiler-get", "params": {"token": "abc123"}}, {"tool": "monolog-search", "params": {"level": "ERROR"}}]'

  <comment># Mix tool calls and resource reads</comment>
  %command.full_name% --json='[{"tool": "symfony-profiler-list", "params": {"limit": 1}}, {"resource": "symfony-profiler://profile/abc123/db"}]'

  <comment># Chain calls: name a call with "id", use its result as {{id.path}}, hide it with "output": false, trim with "pick"</comment>
  %command.full_name% --json='[{"id": "list", "tool": "symfony-profiler-list", "params": {"limit": 1}, "output": false}, {"tool": "symfony-profiler-get", "params": {"token": "{{list.profiles[0].token}}"}, "pick": ["request.status_code"]}]'

  <comment># JSON output format for scripting</comment>
  %command.full_name% --json='[{"tool": "server-info"}]' --format=json

  <comment># For a single tool call, use:</comment>
  {$script} tools:call <tool-name>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = $input->getOption('format');
        $format = \is_string($format) ? $format : 'pretty';

        if (!$this->ensureFormatSupported($io, $format, ['pretty', 'json', 'toon'])) {
            return Command::FAILURE;
        }

        $jsonOption = $input->getOption('json');
        if (!\is_string($jsonOption) || '' === $jsonOption) {
            $io->error('Not enough arguments (missing: "--json").');

            return Command::FAILURE;
        }

        $decoded = json_decode($jsonOption, true);
        if (\JSON_ERROR_NONE !== json_last_error()) {
            $io->error(\sprintf('Invalid JSON in --json: %s', json_last_error_msg()));

            return Command::FAILURE;
        }

        $validationError = $this->validateCalls($decoded);
        if (null !== $validationError) {
            $io->error($validationError);

            return Command::FAILURE;
        }

        /** @var list<BatchCall> $decoded */
        $results = $this->dispatch($decoded);

        $shown = [];
        foreach ($results as $index => $result) {
            if (false !== ($decoded[$index]['output'] ?? true) || !$result['ok']) {
                $shown[] = $result;
            }
        }

        if ('json' === $format) {
            $output->writeln(json_encode($shown, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } elseif ('toon' === $format) {
            $output->writeln(Toon::encode($shown));
        } else {
            $this->renderResultsPretty($shown, \count($results), \count(array_filter($results, static fn (array $r): bool => !$r['ok'])), $io, $output);
        }

        foreach ($results as $result) {
            if (!$result['ok']) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Validates the decoded --json payload without executing anything, so a malformed
     * request is reported as one clear error instead of one confusing error per call.
     */
    private function validateCalls(mixed $decoded): ?string
    {
        if (!\is_array($decoded) || !array_is_list($decoded)) {
            return 'The --json value must be a JSON array of call objects.';
        }

        if ([] === $decoded) {
            return 'The batch must contain at least one call.';
        }

        $allIds = [];
        foreach ($decoded as $call) {
            if (\is_array($call) && isset($call['id']) && \is_string($call['id'])) {
                $allIds[] = $call['id'];
            }
        }

        $earlierIds = [];
        foreach ($decoded as $index => $call) {
            if (!\is_array($call)) {
                return \sprintf('Call at index %d must be an object with a "tool" or a "resource".', $index);
            }

            if (null !== $error = $this->validateCall($index, $call)) {
                return $error;
            }

            if (isset($call['id']) && \in_array($call['id'], $earlierIds, true)) {
                return \sprintf('Call at index %d reuses the id "%s", ids must be unique.', $index, $call['id']);
            }

            try {
                $references = [...ReferenceResolver::collect($call['params'] ?? []), ...ReferenceResolver::collect($call['resource'] ?? '')];
            } catch (InvalidArgumentException $e) {
                return \sprintf('Call at index %d: %s', $index, $e->getMessage());
            }

            foreach ($references as $reference) {
                if (\in_array($reference['id'], $earlierIds, true)) {
                    continue;
                }

                return \in_array($reference['id'], $allIds, true)
                    ? \sprintf('Call at index %d uses %s, but call "%s" does not run before it. A reference may only point to an earlier call.', $index, $reference['expression'], $reference['id'])
                    : \sprintf('Call at index %d uses %s, but no call has the id "%s". Earlier ids: %s.', $index, $reference['expression'], $reference['id'], [] === $earlierIds ? 'none' : implode(', ', $earlierIds));
            }

            if (isset($call['id'])) {
                $earlierIds[] = $call['id'];
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $call
     */
    private function validateCall(int $index, array $call): ?string
    {
        if ([] !== $unknown = array_diff(array_keys($call), ['id', 'tool', 'params', 'resource', 'output', 'pick'])) {
            return \sprintf('Call at index %d has unknown key "%s", only "id", "tool", "params", "resource", "output" and "pick" are allowed.', $index, implode('", "', array_map(strval(...), $unknown)));
        }

        if (\array_key_exists('id', $call)) {
            if (!\is_string($call['id']) || 1 !== preg_match('/^[a-z][a-z0-9_]*$/', $call['id'])) {
                return \sprintf('Call at index %d has an invalid "id", it must match [a-z][a-z0-9_]*.', $index);
            }
        }

        if (isset($call['output']) && !\is_bool($call['output'])) {
            return \sprintf('Call at index %d has a non-boolean "output" value.', $index);
        }

        if (\array_key_exists('pick', $call)) {
            if (!\is_array($call['pick']) || [] === $call['pick'] || !array_is_list($call['pick'])) {
                return \sprintf('Call at index %d has an invalid "pick", it must be a non-empty list of paths.', $index);
            }
            foreach ($call['pick'] as $path) {
                if (!\is_string($path)) {
                    return \sprintf('Call at index %d has a non-string entry in "pick".', $index);
                }
                try {
                    PathResolver::assertValid($path);
                } catch (InvalidArgumentException $e) {
                    return \sprintf('Call at index %d: %s', $index, $e->getMessage());
                }
            }
        }

        $hasTool = isset($call['tool']);
        $hasResource = isset($call['resource']);

        if ($hasTool === $hasResource) {
            return \sprintf('Call at index %d needs exactly one of "tool" or "resource".', $index);
        }

        if ($hasResource) {
            if (!\is_string($call['resource']) || '' === $call['resource']) {
                return \sprintf('Call at index %d has an empty "resource" URI.', $index);
            }

            if (isset($call['params'])) {
                return \sprintf('Call at index %d ("%s") is a resource read and cannot have "params".', $index, $call['resource']);
            }

            return null;
        }

        if (!\is_string($call['tool']) || '' === $call['tool']) {
            return \sprintf('Call at index %d is missing a "tool" name.', $index);
        }

        if (isset($call['params']) && !\is_array($call['params'])) {
            return \sprintf('Call at index %d ("%s") has a non-object "params" value.', $index, $call['tool']);
        }

        return null;
    }

    /**
     * @param list<BatchCall> $calls
     *
     * @return list<BatchResult>
     */
    private function dispatch(array $calls): array
    {
        $results = [];
        // Full results of the earlier calls, so a pick never changes what a reference sees.
        $raw = [];
        $failed = [];
        $skipped = [];

        foreach ($calls as $call) {
            $id = $call['id'] ?? null;
            $label = isset($call['resource']) ? ['resource' => $call['resource']] : ['tool' => $call['tool'] ?? ''];

            try {
                $dependency = $this->findSkippedDependency($call, $skipped);
                if (null !== $dependency) {
                    throw new RuntimeException(\sprintf('depends on failed call %s', $dependency));
                }

                $resource = isset($call['resource']) ? ReferenceResolver::resolve($call['resource'], $raw, $failed) : null;
                if (isset($call['resource']) && !\is_string($resource)) {
                    throw new RuntimeException(\sprintf('The resource URI "%s" did not resolve to a string.', $call['resource']));
                }
                $params = isset($call['resource']) ? [] : ReferenceResolver::resolve($call['params'] ?? [], $raw, $failed);
            } catch (RuntimeException $e) {
                $entry = $label + ['ok' => false, 'result' => null, 'error' => $e->getMessage()];
                if (null !== $id) {
                    $skipped[$id] = true;
                }
                $results[] = null !== $id ? ['id' => $id] + $entry : $entry;

                continue;
            }

            /** @var array<string, mixed> $params */
            $entry = \is_string($resource) ? $this->readResource($resource) : $this->dispatchOne($call['tool'] ?? '', $params);
            if (null !== $id) {
                if ($entry['ok']) {
                    $raw[$id] = $entry['result'];
                } else {
                    $failed[$id] = \sprintf('call %s failed: %s', $id, $entry['error']);
                }
            }

            if ($entry['ok'] && isset($call['pick'])) {
                $entry = $this->applyPick($entry, $call['pick']);
            }

            $results[] = null !== $id ? ['id' => $id] + $entry : $entry;
        }

        return $results;
    }

    /**
     * @param BatchCall           $call
     * @param array<string, true> $skipped
     */
    private function findSkippedDependency(array $call, array $skipped): ?string
    {
        foreach ([...ReferenceResolver::collect($call['params'] ?? []), ...ReferenceResolver::collect($call['resource'] ?? '')] as $reference) {
            if (isset($skipped[$reference['id']])) {
                return $reference['id'];
            }
        }

        return null;
    }

    /**
     * @param BatchResult  $entry
     * @param list<string> $paths
     *
     * @return BatchResult
     */
    private function applyPick(array $entry, array $paths): array
    {
        $result = $entry['result'];
        $content = PathResolver::unwrap($result);

        $picked = [];
        $missing = [];
        foreach ($paths as $path) {
            try {
                $picked[$path] = PathResolver::resolve($content, $path);
            } catch (RuntimeException) {
                $picked[$path] = null;
                $missing[] = $path;
            }
        }

        if (\is_array($result) && \array_key_exists('untrusted_data', $result)) {
            $entry['result'] = (isset($result['_security_notice']) ? ['_security_notice' => $result['_security_notice']] : []) + ['untrusted_data' => $picked];
        } else {
            $entry['result'] = $picked;
        }

        if ([] !== $missing) {
            $entry['missing_paths'] = $missing;
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return BatchResult
     */
    private function dispatchOne(string $toolName, array $params): array
    {
        $tool = $this->registry->findTool($toolName);
        if (null === $tool) {
            return ['tool' => $toolName, 'ok' => false, 'result' => null, 'error' => \sprintf('Tool "%s" not found', $toolName)];
        }

        try {
            $result = $this->invoker->invoke($tool, $params);
        } catch (\Throwable $e) {
            return ['tool' => $toolName, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }

        if (\is_string($result)) {
            $result = ResponseEncoder::tryDecode($result);
        }

        return ['tool' => $toolName, 'ok' => true, 'result' => $result, 'error' => null];
    }

    /**
     * @return BatchResult
     */
    private function readResource(string $uri): array
    {
        try {
            $read = $this->reader->read($uri);
        } catch (ResourceNotFoundException) {
            return ['resource' => $uri, 'ok' => false, 'result' => null, 'error' => \sprintf('Resource "%s" not found', $uri)];
        } catch (\Throwable $e) {
            return ['resource' => $uri, 'ok' => false, 'result' => null, 'error' => $e->getMessage()];
        }

        $contents = [];
        foreach ($read['contents'] as $content) {
            $contents[] = isset($content['text']) ? ResponseEncoder::tryDecode($content['text']) : $content;
        }

        return ['resource' => $uri, 'ok' => true, 'result' => 1 === \count($contents) ? $contents[0] : $contents, 'error' => null];
    }

    /**
     * @param list<BatchResult> $results
     */
    private function renderResultsPretty(array $results, int $total, int $failedCount, SymfonyStyle $io, OutputInterface $output): void
    {
        foreach ($results as $result) {
            $name = $result['tool'] ?? $result['resource'] ?? '';
            $io->section(isset($result['id']) ? \sprintf('%s (%s)', $name, $result['id']) : $name);

            if (!$result['ok']) {
                $io->error($result['error'] ?? 'Unknown error');
            } elseif ($this->isTooLargeToRenderPretty($result['result'])) {
                $this->renderJsonFallback($result['result'], $io, $output);
            } else {
                $this->renderPretty($result['result'], $io);
            }

            if (isset($result['missing_paths'])) {
                $io->warning(\sprintf('Paths without a value: %s', implode(', ', $result['missing_paths'])));
            }
        }

        $io->newLine();
        $io->text(\sprintf('%d of %d calls succeeded.', $total - $failedCount, $total));
    }
}
