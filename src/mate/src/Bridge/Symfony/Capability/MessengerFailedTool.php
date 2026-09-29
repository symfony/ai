<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Capability;

use Symfony\AI\Mate\Attribute\MateTool;
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\UndecodableMessageException;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ContainerConfiguration;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\DoctrineTransportReader;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\EnvelopeDecoder;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\FailedMessageGrouper;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ProjectEnvironment;
use Symfony\AI\Mate\Bridge\Symfony\Service\ContainerProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;

/**
 * Lists the messages in the Messenger failure transports, grouped by cause.
 *
 * Reads the transport storage directly, so it works when the kernel does not boot, and never
 * changes it. The transports come from the compiled container, their DSNs are resolved against the
 * project's .env files and the real environment.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MessengerFailedTool
{
    public const MAX_SCAN = 5000;
    private const MAX_UNDECODABLE = 10;

    /**
     * @var array<string|int, string>
     */
    private readonly array $cacheDirs;

    /**
     * @param string|array<string, string> $cacheDir A single cache directory, or a map of context name to cache
     *                                               directory for multi-kernel (APP_ID) applications
     */
    public function __construct(
        private readonly string $projectDir,
        string|array $cacheDir,
        private readonly ContainerProvider $provider = new ContainerProvider(),
        private readonly EnvelopeDecoder $decoder = new EnvelopeDecoder(),
        private readonly DoctrineTransportReader $doctrine = new DoctrineTransportReader(),
    ) {
        $this->cacheDirs = \is_string($cacheDir) ? [0 => $cacheDir] : $cacheDir;
    }

    /**
     * @param string|null $transport Read only this transport (any configured transport name); default: every failure transport
     * @param string|null $group     List the messages of this group (its group_id from the summary)
     * @param string|null $context   Kernel context, required when several cache directories are configured
     */
    #[MateTool(name: 'symfony-messenger-failed', title: 'Symfony Messenger Failed Messages', description: 'List the messages in the Messenger failure transports grouped by cause (exception class, message pattern, failing application frame), largest group first (at most 50 groups, each with a group_id for the group parameter), read from the Doctrine transport storage without booting the kernel. Other transports return an error pointing at messenger:failed:show.')]
    public function failed(?string $transport = null, ?string $group = null, ?string $context = null): string
    {
        $environment = new ProjectEnvironment($this->projectDir);
        $configuration = ContainerConfiguration::load($this->cacheDir($context), $this->provider, $environment, $this->projectDir);

        $receivers = $configuration->receivers();
        $selected = array_values(array_filter($receivers, static fn (array $r): bool => null === $transport ? $r['failure'] : $r['name'] === $transport));
        if ([] === $selected) {
            $names = array_map(static fn (array $r): string => $r['name'], $receivers);
            throw new InvalidArgumentException(null === $transport ? \sprintf('No failure transport is configured (framework.messenger.failure_transport). Configured transports: "%s".', implode('", "', $names)) : \sprintf('Transport "%s" not found. Configured transports: "%s".', $transport, implode('", "', $names)));
        }
        if (null !== $group && \count($selected) > 1) {
            throw new InvalidArgumentException(\sprintf('Group ids are per transport and there are several failure transports ("%s"); pass "transport" together with "group".', implode('", "', array_map(static fn (array $r): string => $r['name'], $selected))));
        }

        $result = [];
        foreach ($selected as $receiver) {
            try {
                $result[] = ['transport' => $receiver['name']] + $this->read($receiver['name'], $configuration->resolve($receiver['dsn']), $receiver['options'], $configuration, $group);
            } catch (FailureTransportNotReadableException $e) {
                $result[] = ['transport' => $receiver['name'], 'error' => $e->getMessage()];
            }
        }

        return ResponseEncoder::encodeUntrusted(['container' => $this->containerInfo($configuration), 'transports' => $result]);
    }

    /**
     * @return array{path: string, skipped: list<string>}
     */
    private function containerInfo(ContainerConfiguration $configuration): array
    {
        $path = str_starts_with($configuration->path, $this->projectDir.'/') ? substr($configuration->path, \strlen($this->projectDir) + 1) : $configuration->path;

        return ['path' => $path, 'skipped' => $configuration->skipped];
    }

    private function cacheDir(?string $context): string
    {
        if (null !== $context && '' !== $context) {
            return $this->cacheDirs[$context] ?? throw new InvalidArgumentException(\sprintf('Unknown context "%s". Known contexts: "%s".', $context, implode('", "', array_map(strval(...), array_keys($this->cacheDirs)))));
        }
        if (\count($this->cacheDirs) > 1) {
            throw new InvalidArgumentException(\sprintf('Several kernel contexts are configured; pass "context" (one of "%s").', implode('", "', array_map(strval(...), array_keys($this->cacheDirs)))));
        }

        return $this->cacheDirs[array_key_first($this->cacheDirs)] ?? '';
    }

    /**
     * @param array<string, string> $options
     *
     * @return array{storage: string, message_count: int, scanned: int, scan_truncated: bool, scan_truncated_reason?: string, group_count: int, groups_truncated: bool, omitted_groups: int, omitted_messages: int, undecodable: array{count: int, messages: list<array{id: string, reason: string}>}, groups: list<array<string, mixed>>}
     */
    private function read(string $name, string $dsn, array $options, ContainerConfiguration $configuration, ?string $group): array
    {
        $scheme = strtolower((string) strtok($dsn, ':'));
        $showCommand = \sprintf('Use "bin/console messenger:failed:show --transport=%s" instead.', $name);

        $unsupported = match (true) {
            'doctrine' === $scheme => null,
            'in-memory' === $scheme => 'An in-memory transport keeps its messages in the memory of the process that sent them; there is no storage to read.',
            'sync' === $scheme => 'A sync transport handles messages immediately and stores nothing.',
            str_starts_with($scheme, 'redis') || str_starts_with($scheme, 'valkey') => 'Redis transports are not read by this tool yet.',
            str_starts_with($scheme, 'amqp') => 'An AMQP queue cannot be read without receiving its messages, which changes their state.',
            'sqs' === $scheme || str_contains($dsn, 'sqs.') => 'An SQS queue cannot be read without receiving its messages, which changes their visibility.',
            'beanstalkd' === $scheme => 'Beanstalkd transports are not read by this tool.',
            default => \sprintf('Unknown transport kind "%s".', $scheme),
        };
        if (null !== $unsupported) {
            throw new FailureTransportNotReadableException($unsupported.' '.$showCommand);
        }

        $messages = [];
        $undecodable = [];
        $data = $this->doctrine->read($dsn, $options, $configuration, self::MAX_SCAN, function (string $id, string $body, array $headers, ?\DateTimeImmutable $storedAt) use (&$messages, &$undecodable): void {
            try {
                $messages[] = $this->decoder->decode($id, $body, $headers, $storedAt);
            } catch (UndecodableMessageException $e) {
                $undecodable[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        });
        $undecodable = array_merge($data['skipped'], $undecodable);
        usort($undecodable, static fn (array $a, array $b): int => [(int) $a['id'], $a['id']] <=> [(int) $b['id'], $b['id']]);

        // Read newest first; listed oldest first.
        $grouped = (new FailedMessageGrouper(array_values(array_unique(array_filter([$this->projectDir, $configuration->compiledProjectDir()])))))->group(array_reverse($messages), $group);
        if (null !== $group && [] === $grouped['groups']) {
            throw new InvalidArgumentException(\sprintf('Transport "%s" has no group "%s".', $name, $group));
        }

        $result = [
            'storage' => $data['storage'],
            'message_count' => $data['total'],
            'scanned' => $data['scanned'],
            'scan_truncated' => null !== $data['truncated'],
        ];
        if (null !== $data['truncated']) {
            $result['scan_truncated_reason'] = $data['truncated'];
        }

        return $result + [
            'group_count' => $grouped['group_count'],
            'groups_truncated' => $grouped['groups_truncated'],
            'omitted_groups' => $grouped['omitted_groups'],
            'omitted_messages' => $grouped['omitted_messages'],
            'undecodable' => ['count' => \count($undecodable), 'messages' => \array_slice($undecodable, 0, self::MAX_UNDECODABLE)],
            'groups' => $grouped['groups'],
        ];
    }
}
