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
use Symfony\AI\Mate\Bridge\Symfony\Exception\ContainerNotDumpedException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\FailureTransportNotReadableException;
use Symfony\AI\Mate\Bridge\Symfony\Exception\UndecodableMessageException;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ContainerConfiguration;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\DoctrineTransportReader;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\EnvelopeDecoder;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\FailedMessageGrouper;
use Symfony\AI\Mate\Bridge\Symfony\Messenger\ProjectEnvironment;
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
     * @var list<string>
     */
    private readonly array $cacheDirs;

    /**
     * @param string|array<string, string> $cacheDir A single cache directory, or a map of context name to cache directory
     */
    public function __construct(
        private readonly string $projectDir,
        string|array $cacheDir,
        private readonly EnvelopeDecoder $decoder = new EnvelopeDecoder(),
        private readonly DoctrineTransportReader $doctrine = new DoctrineTransportReader(),
    ) {
        $this->cacheDirs = \is_string($cacheDir) ? [$cacheDir] : array_values($cacheDir);
    }

    /**
     * @param string|null $transport Read this transport only (any configured transport name). Default: every failure transport.
     * @param int|null    $group     List every message of this group (1-based, as numbered in the grouped result)
     */
    #[MateTool(name: 'symfony-messenger-failed', title: 'Symfony Messenger Failed Messages', description: 'List the messages in the Messenger failure transports grouped by cause (exception class, message pattern, failing application frame), largest group first. Per group: count, message classes, the frame that threw, sample exception messages, trace, retry counts, first and last failure time, message ids. Every cause is listed however small, so a rare real bug is not hidden behind a flood of one transient failure. Reads the storage directly and read-only (works when the kernel does not boot; never locks, acks or removes a message). Supported: Doctrine transports, with the PHP serializer or the Symfony Serializer. Redis, AMQP, SQS, Beanstalkd and in-memory transports return an error pointing at messenger:failed:show. Pass group to list every message of one group, transport to read one transport.')]
    public function failed(?string $transport = null, ?int $group = null): string
    {
        if (null !== $group && $group < 1) {
            throw new InvalidArgumentException('The "group" parameter is 1-based.');
        }

        $environment = new ProjectEnvironment($this->projectDir);
        try {
            $configuration = ContainerConfiguration::load($this->cacheDirs, $environment, $this->projectDir);
        } catch (ContainerNotDumpedException $e) {
            throw new ContainerNotDumpedException($e->getMessage().' Without it, use "bin/console messenger:failed:show".', 0, $e);
        }

        $receivers = $configuration->receivers();
        $selected = array_values(array_filter($receivers, static fn (array $r): bool => null === $transport ? $r['failure'] : $r['name'] === $transport));
        if ([] === $selected) {
            $names = array_map(static fn (array $r): string => $r['name'], $receivers);
            throw new InvalidArgumentException(null === $transport ? \sprintf('No failure transport is configured (framework.messenger.failure_transport). Configured transports: "%s".', implode('", "', $names)) : \sprintf('Transport "%s" not found. Configured transports: "%s".', $transport, implode('", "', $names)));
        }
        if (null !== $group && \count($selected) > 1) {
            throw new InvalidArgumentException(\sprintf('Groups are numbered per transport and there are several failure transports ("%s"); pass "transport" together with "group".', implode('", "', array_map(static fn (array $r): string => $r['name'], $selected))));
        }

        $result = [];
        foreach ($selected as $receiver) {
            try {
                $result[] = ['transport' => $receiver['name']] + $this->read($receiver['name'], $configuration->resolve($receiver['dsn']), $receiver['options'], $configuration, $group);
            } catch (FailureTransportNotReadableException $e) {
                $result[] = ['transport' => $receiver['name'], 'error' => $e->getMessage()];
            }
        }

        return ResponseEncoder::encodeUntrusted(['transports' => $result]);
    }

    /**
     * @param array<string, string> $options
     *
     * @return array<string, mixed>
     */
    private function read(string $name, string $dsn, array $options, ContainerConfiguration $configuration, ?int $group): array
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

        $data = $this->doctrine->read($dsn, $options, $configuration, self::MAX_SCAN);

        $messages = [];
        $undecodable = [];
        foreach ($data['rows'] as $row) {
            try {
                $messages[] = $this->decoder->decode($row['id'], $row['body'], $row['headers'], $row['created_at']);
            } catch (UndecodableMessageException $e) {
                $undecodable[] = ['id' => $row['id'], 'reason' => $e->getMessage()];
            }
        }

        $groups = (new FailedMessageGrouper(array_values(array_unique(array_filter([$this->projectDir, $configuration->compiledProjectDir()])))))->group($messages, $group);
        if (null !== $group && [] === $groups) {
            throw new InvalidArgumentException(\sprintf('Transport "%s" has no group %d.', $name, $group));
        }

        return [
            'storage' => $data['storage'],
            'message_count' => $data['total'],
            'scanned' => \count($data['rows']),
            'scan_truncated' => $data['total'] > \count($data['rows']),
            'group_count' => null === $group ? \count($groups) : null,
            'undecodable' => ['count' => \count($undecodable), 'messages' => \array_slice($undecodable, 0, self::MAX_UNDECODABLE)],
            'groups' => $groups,
        ];
    }
}
