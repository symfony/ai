<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter;

use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\CollectorFormatterInterface;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Formats security collector data.
 *
 * Reports authentication state, roles, voter decisions, and firewall configuration.
 * Sensitive fields (token, logout URL) are intentionally excluded.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @internal
 *
 * @implements CollectorFormatterInterface<SecurityDataCollector>
 */
final class SecurityCollectorFormatter implements CollectorFormatterInterface
{
    private const MAX_ACCESS_DECISION_LOG = 50;

    public function getName(): string
    {
        return 'security';
    }

    public function format(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof SecurityDataCollector);

        $log = $this->extractArray($collector->getAccessDecisionLog());
        $truncated = \count($log) > self::MAX_ACCESS_DECISION_LOG;
        $log = \array_slice($log, 0, self::MAX_ACCESS_DECISION_LOG);

        return [
            'enabled' => $collector->isEnabled(),
            'authenticated' => $collector->isAuthenticated(),
            'user' => $collector->getUser(),
            'roles' => $this->extractStringArray($collector->getRoles()),
            'inherited_roles' => $this->extractStringArray($collector->getInheritedRoles()),
            'supports_role_hierarchy' => $collector->supportsRoleHierarchy(),
            'impersonated' => $collector->isImpersonated(),
            'impersonator_user' => $collector->getImpersonatorUser(),
            'voter_strategy' => $collector->getVoterStrategy(),
            'voters' => $this->extractStringArray($collector->getVoters()),
            'access_decision_log' => $this->formatAccessDecisionLog($log),
            'access_decision_log_truncated' => $truncated,
            'firewall' => $this->extractNullableArray($collector->getFirewall()),
        ];
    }

    public function getSummary(DataCollectorInterface $collector): array
    {
        \assert($collector instanceof SecurityDataCollector);

        return [
            'enabled' => $collector->isEnabled(),
            'authenticated' => $collector->isAuthenticated(),
            'user' => $collector->getUser(),
            'roles' => $this->extractStringArray($collector->getRoles()),
            'impersonated' => $collector->isImpersonated(),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function extractArray(mixed $data): array
    {
        if (\is_object($data) && method_exists($data, 'getValue')) {
            $data = $data->getValue(true);
        }

        return \is_array($data) ? $data : [];
    }

    /**
     * @return array<mixed>|null
     */
    private function extractNullableArray(mixed $data): ?array
    {
        if (null === $data) {
            return null;
        }

        return $this->extractArray($data);
    }

    /**
     * @return string[]
     */
    private function extractStringArray(mixed $data): array
    {
        $data = $this->extractArray($data);

        return array_values(array_filter(array_map(
            static fn (mixed $v): mixed => \is_string($v) ? $v : null,
            $data,
        )));
    }

    /**
     * @param array<mixed> $log
     *
     * @return list<array<string, mixed>>
     */
    private function formatAccessDecisionLog(array $log): array
    {
        $formatted = [];
        foreach ($log as $entry) {
            if (\is_object($entry) && method_exists($entry, 'getValue')) {
                $entry = $entry->getValue(true);
            }

            if (!\is_array($entry)) {
                continue;
            }

            $object = $entry['object'] ?? null;
            if (\is_object($object) && method_exists($object, 'getValue')) {
                $object = $object->getValue(true);
            }

            $voterDetails = $entry['voter_details'] ?? [];
            if (\is_object($voterDetails) && method_exists($voterDetails, 'getValue')) {
                $voterDetails = $voterDetails->getValue(true);
            }

            $formatted[] = [
                'attribute' => $entry['attribute'] ?? null,
                'object' => \is_object($object) ? $object::class : (\is_string($object) ? $object : null),
                'result' => isset($entry['result']) ? (bool) $entry['result'] : null,
                'voter_details' => \is_array($voterDetails) ? $voterDetails : [],
            ];
        }

        return $formatted;
    }
}
