<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Factory;

use Symfony\AI\Platform\Provider\ProviderConfig;
use Symfony\AI\Platform\Transport\Dsn;

final class ProviderConfigFactory
{
    public static function fromDsn(string|Dsn $dsn): ProviderConfig
    {
        $dsn = \is_string($dsn) ? Dsn::fromString($dsn) : $dsn;

        $provider = strtolower($dsn->getProvider());
        if ('' === $provider) {
            throw new \InvalidArgumentException('DSN must include a provider (e.g. "ai+openai://...").');
        }

        $host = $dsn->getHost();
        if ('' === $host) {
            $host = self::defaultHostOrFail($provider);
        }

        $scheme = 'https';
        $port = $dsn->getPort();
        $baseUri = $scheme.'://'.$host.($port ? ':'.$port : '');

        $q = $dsn->getQuery();

        $headers = [];

        if (isset($q['headers']) && \is_array($q['headers'])) {
            foreach ($q['headers'] as $hk => $hv) {
                $headers[$hk] = $hv;
            }
        }

        foreach ($q as $k => $v) {
            if (preg_match('/^headers\[(.+)\]$/', (string) $k, $m)) {
                $headers[$m[1]] = $v;
                continue;
            }
            if (str_starts_with((string) $k, 'headers_')) {
                $hk = substr((string) $k, \strlen('headers_'));
                if ('' !== $hk) {
                    $headers[$hk] = $v;
                }
            }
        }

        $options = array_filter([
            'model' => $q['model'] ?? null,
            'version' => $q['version'] ?? null,
            'deployment' => $q['deployment'] ?? null,
            'organization' => $q['organization'] ?? null,
            'location' => $q['location'] ?? ($q['region'] ?? null),
            'timeout' => isset($q['timeout']) ? (int) $q['timeout'] : null,
            'verify_peer' => isset($q['verify_peer']) ? self::toBool($q['verify_peer']) : null,
            'proxy' => $q['proxy'] ?? null,
        ], static fn ($v) => null !== $v && '' !== $v);

        switch ($provider) {
            case 'azure':
                $engine = strtolower((string) ($q['engine'] ?? 'openai'));
                if (!\in_array($engine, ['openai', 'meta'], true)) {
                    throw new \InvalidArgumentException(\sprintf('Unsupported Azure engine "%s". Supported: "openai", "meta".', $engine));
                }
                $options['engine'] = $engine;

                if ('' === $dsn->getHost()) {
                    throw new \InvalidArgumentException('Azure DSN requires host: "<resource>.openai.azure.com" or "<resource>.meta.azure.com".');
                }
                if (!isset($options['deployment']) || '' === $options['deployment']) {
                    throw new \InvalidArgumentException('Azure DSN requires "deployment" query param.');
                }
                if (!isset($options['version']) || '' === $options['version']) {
                    throw new \InvalidArgumentException('Azure DSN requires "version" query param.');
                }
                break;

            case 'openai':
            case 'anthropic':
            case 'gemini':
            case 'vertex':
            case 'ollama':
                break;

            default:
                throw new \InvalidArgumentException(\sprintf('Unknown AI provider "%s".', $provider));
        }

        return new ProviderConfig(
            provider: $provider,
            baseUri: $baseUri,
            apiKey: $dsn->getUser(),
            options: $options,
            headers: $headers
        );
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        $v = strtolower((string) $value);

        return \in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    private static function defaultHostOrFail(string $provider): string
    {
        return match ($provider) {
            'openai' => 'api.openai.com',
            'anthropic' => 'api.anthropic.com',
            'gemini' => 'generativelanguage.googleapis.com',
            'vertex' => 'us-central1-aiplatform.googleapis.com',
            'ollama' => 'localhost',
            'azure' => throw new \InvalidArgumentException('Azure DSN must specify host (e.g. "<resource>.openai.azure.com").'),
            default => throw new \InvalidArgumentException(\sprintf('Unknown AI provider "%s".', $provider)),
        };
    }
}
