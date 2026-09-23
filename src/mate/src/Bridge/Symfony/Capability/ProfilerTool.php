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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Exception\InvalidCollectorException;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileIndex;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\ProfilerDataProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Mate\Exception\InvalidArgumentException;
use Symfony\AI\Mate\Exception\RuntimeException;

/**
 * Tools for accessing Symfony profiler data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfilerTool
{
    /**
     * The field that decides a comparison's verdict, per collector: a lower value on
     * `current` than on `baseline` counts as improved. A collector mapped to null has no
     * field anyone can call unambiguously better when lower (sending more or fewer emails
     * isn't inherently good; a request's own fields describe what was asked, not whether
     * the outcome was good), so compare() reports its delta but never a verdict beyond
     * `unchanged`. An unlisted collector is treated the same way: guessing a direction from
     * whichever field happens to come first is exactly the bug this map exists to avoid.
     *
     * @var array<string, string|null>
     */
    private const LEADING_METRICS = [
        'db' => 'query_count',
        'time' => 'duration_ms',
        'memory' => 'memory_mb',
        'logger' => 'error_count',
        'translation' => 'count_missings',
        'exception' => 'has_exception',
        'mailer' => null,
        'request' => null,
    ];

    public function __construct(
        private readonly ?ProfilerDataProvider $dataProvider = null,
    ) {
    }

    /**
     * @param int         $limit      Maximum number of profiles to return (use limit=1 to get the latest profile)
     * @param string|null $method     Filter by HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string|null $url        Filter by URL path (partial match supported)
     * @param string|null $ip         Filter by client IP address
     * @param int|null    $statusCode Filter by HTTP response status code (e.g. 200, 404, 500)
     * @param string|null $context    Filter by Symfony kernel context
     * @param string|null $from       Start date filter for profile creation time
     * @param string|null $to         End date filter for profile creation time
     */
    #[MateTool(name: 'symfony-profiler-list', title: 'Symfony Profiler List', description: 'List and filter Symfony profiler profiles by HTTP method, URL, IP, status code, date range, or context. Profiles are sorted by most recent first, so limit=1 returns the latest profile. Returns summary data with resource_uri for fetching full details via the resource template.')]
    public function listProfiles(
        int $limit = 20,
        ?string $method = null,
        ?string $url = null,
        ?string $ip = null,
        ?int $statusCode = null,
        ?string $context = null,
        ?string $from = null,
        ?string $to = null,
    ): string {
        $dataProvider = $this->getDataProvider();
        $criteria = [
            'context' => $context,
            'method' => $method,
            'url' => $url,
            'ip' => $ip,
            'statusCode' => $statusCode,
            'from' => $from,
            'to' => $to,
        ];

        $profiles = $dataProvider->searchProfiles(array_filter($criteria), $limit);

        return ResponseEncoder::encodeUntrusted([
            'profiles' => array_values(array_map(
                static fn (ProfileIndex $profile): array => $profile->toArray(),
                $profiles,
            )),
        ]);
    }

    /**
     * @param string $token The unique profiler token identifying the profile
     */
    #[MateTool(name: 'symfony-profiler-get', title: 'Symfony Profiler Get', description: 'Get a specific profiler profile by its token. Returns detailed profile data including available collectors and resource_uri for accessing collector-specific data.')]
    public function getProfile(string $token): string
    {
        $profileData = $this->getDataProvider()->findProfile($token);

        if (null === $profileData) {
            throw new InvalidArgumentException(\sprintf('Profile with token "%s" not found', $token));
        }

        $profile = $profileData->getProfile();

        // The description promises the available collectors, and without them this returns the
        // same nine fields the listing already gave, leaving no way to reach the actual data.
        $collectors = [];
        foreach ($this->getDataProvider()->listAvailableCollectors($token) as $collectorName) {
            $collectors[] = [
                'name' => $collectorName,
                'uri' => \sprintf('symfony-profiler://profile/%s/%s', $token, $collectorName),
            ];
        }

        $data = [
            'token' => $profile->getToken(),
            'ip' => $profile->getIp(),
            'method' => $profile->getMethod(),
            'url' => $profile->getUrl(),
            'time' => $profile->getTime(),
            'time_formatted' => date(\DateTimeInterface::ATOM, $profile->getTime()),
            'status_code' => $profile->getStatusCode(),
            'parent_token' => $profile->getParentToken(),
            'resource_uri' => \sprintf('symfony-profiler://profile/%s', $profile->getToken()),
            'collectors' => $collectors,
        ];

        if (null !== $profileData->getContext()) {
            $data['context'] = $profileData->getContext();
        }

        return ResponseEncoder::encodeUntrusted($data);
    }

    /**
     * @param string $baseline  The profiler token measured before the change, or several comma-separated tokens averaged to smooth out run-to-run noise
     * @param string $current   The profiler token measured after the change, or several comma-separated tokens averaged to smooth out run-to-run noise
     * @param string $collector The collector to compare (e.g. db, time, memory, logger)
     */
    #[MateTool(name: 'symfony-profiler-compare', title: 'Symfony Profiler Compare', description: 'Compare the collector summary of two profiler profiles to prove whether a change actually improved a measurement. Reproduce the request after your fix, then compare the new token against the token you captured before. Pass several comma-separated tokens on either side to average multiple runs, which is more reliable than a single run when the measurement has any noise. Returns both summaries, the numeric difference for every shared numeric field, the raw before/after pair for every shared field that changed but is not numeric, and a verdict (improved, unchanged, regressed).')]
    public function compare(string $baseline, string $current, string $collector = 'db'): string
    {
        $baselineTokens = $this->splitTokens($baseline);
        $currentTokens = $this->splitTokens($current);

        $baselineSummary = $this->averagedSummary($baselineTokens, $collector);
        $currentSummary = $this->averagedSummary($currentTokens, $collector);

        $delta = [];
        $changed = [];
        foreach ($currentSummary as $key => $currentValue) {
            if (!\array_key_exists($key, $baselineSummary)) {
                continue;
            }

            $baselineValue = $baselineSummary[$key];
            $bothNumeric = (\is_int($currentValue) || \is_float($currentValue)) && (\is_int($baselineValue) || \is_float($baselineValue));

            if ($bothNumeric) {
                $difference = $currentValue - $baselineValue;
                $delta[$key] = \is_float($difference) ? round($difference, 2) : $difference;

                continue;
            }

            // Only a numeric field gets a magnitude; anything else that changed is still
            // reported, just as the raw pair, so a boolean or string flip is never silent.
            if ($currentValue !== $baselineValue) {
                $changed[$key] = ['baseline' => $baselineValue, 'current' => $currentValue];
            }
        }

        return ResponseEncoder::encode([
            'collector' => $collector,
            'baseline' => array_merge($this->describeRuns($baselineTokens), $baselineSummary),
            'current' => array_merge($this->describeRuns($currentTokens), $currentSummary),
            'delta' => $delta,
            'changed' => $changed,
            'verdict' => $this->buildVerdict($collector, $baselineSummary, $currentSummary),
        ]);
    }

    /**
     * @return list<string>
     */
    private function splitTokens(string $tokens): array
    {
        $split = array_values(array_filter(array_map(trim(...), explode(',', $tokens))));

        if ([] === $split) {
            throw new InvalidArgumentException('At least one profiler token is required.');
        }

        return $split;
    }

    /**
     * @param list<string> $tokens
     *
     * @return array<string, mixed>
     */
    private function describeRuns(array $tokens): array
    {
        if (1 === \count($tokens)) {
            return ['token' => $tokens[0]];
        }

        return ['tokens' => $tokens, 'run_count' => \count($tokens)];
    }

    /**
     * A single token's summary is returned as-is. Several are averaged field by field, so
     * one slow or flaky run does not decide the comparison: a numeric field (a bool counts
     * as 0/1, so a flakiness rate like "has_exception: 0.4" is a legitimate answer) becomes
     * its mean across the runs. A field every run agrees on, such as a fixed collector name,
     * is kept as-is; one the runs disagree on has no single representative value and is
     * dropped rather than guessed from whichever run happened to be averaged last.
     *
     * @param list<string> $tokens
     *
     * @return array<string, mixed>
     */
    private function averagedSummary(array $tokens, string $collector): array
    {
        $summaries = array_map(fn (string $token): array => $this->getCollectorSummary($token, $collector), $tokens);

        if (1 === \count($summaries)) {
            return $summaries[0];
        }

        $keys = array_unique(array_merge(...array_map(array_keys(...), $summaries)));

        $averaged = [];
        foreach ($keys as $key) {
            $values = array_map(static fn (array $summary): mixed => $summary[$key] ?? null, $summaries);
            $numbers = array_map($this->toComparableNumber(...), $values);

            if (!\in_array(null, $numbers, true)) {
                $averaged[$key] = round(array_sum($numbers) / \count($numbers), 2);

                continue;
            }

            $first = $values[0];
            if ([] === array_filter($values, static fn (mixed $value): bool => $value !== $first)) {
                $averaged[$key] = $first;
            }
        }

        return $averaged;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCollectorSummary(string $token, string $collector): array
    {
        $summary = $this->getDataProvider()->getCollectorData($token, $collector)['summary'];

        if ([] === $summary) {
            throw new InvalidCollectorException(\sprintf('Collector "%s" of profile "%s" does not provide a summary to compare', $collector, $token));
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $baselineSummary
     * @param array<string, mixed> $currentSummary
     */
    private function buildVerdict(string $collector, array $baselineSummary, array $currentSummary): string
    {
        $leadingMetric = self::LEADING_METRICS[$collector] ?? null;
        if (null === $leadingMetric) {
            return 'unchanged';
        }

        $baselineNumber = $this->toComparableNumber($baselineSummary[$leadingMetric] ?? null);
        $currentNumber = $this->toComparableNumber($currentSummary[$leadingMetric] ?? null);
        if (null === $baselineNumber || null === $currentNumber) {
            return 'unchanged';
        }

        $difference = $currentNumber - $baselineNumber;

        if ($difference < 0) {
            return 'improved';
        }

        if ($difference > 0) {
            return 'regressed';
        }

        return 'unchanged';
    }

    /**
     * Booleans compare as 0/1, so a leading metric like `has_exception` still has a
     * direction: false is lower, so losing the exception counts as the improvement.
     */
    private function toComparableNumber(mixed $value): int|float|null
    {
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        if (\is_bool($value)) {
            return $value ? 1 : 0;
        }

        return null;
    }

    private function getDataProvider(): ProfilerDataProvider
    {
        if (null === $this->dataProvider) {
            throw new RuntimeException('Symfony profiler tools are not available in this Mate workspace.');
        }

        return $this->dataProvider;
    }
}
