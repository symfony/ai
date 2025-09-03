<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Transport;

final class Dsn
{
    /**
     * @param array<string,mixed> $query
     */
    public function __construct(private string $scheme, private string $host = '', private ?int $port = null, private ?string $user = null, private ?string $password = null, private array $query = [])
    {
    }

    public static function fromString(string $dsn): self
    {
        if (!preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*)://#', $dsn, $m)) {
            throw new \InvalidArgumentException(\sprintf('Invalid DSN "%s": missing scheme.', $dsn));
        }
        $scheme = $m[1];

        $parts = parse_url($dsn);
        if (false !== $parts && isset($parts['scheme'])) {
            $query = [];
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
            }

            return new self(
                scheme: $parts['scheme'],
                host: $parts['host'] ?? '',
                port: $parts['port'] ?? null,
                user: isset($parts['user']) ? urldecode($parts['user']) : null,
                password: isset($parts['pass']) ? urldecode($parts['pass']) : null,
                query: $query
            );
        }

        $rest = substr($dsn, \strlen($m[0]));
        $queryStr = '';
        if (false !== ($qpos = strpos($rest, '?'))) {
            $queryStr = substr($rest, $qpos + 1);
            $rest = substr($rest, 0, $qpos);
        }

        $user = null;
        $password = null;
        $host = '';
        $port = null;

        if (false !== ($at = strpos($rest, '@'))) {
            $userinfo = substr($rest, 0, $at);
            $rest = substr($rest, $at + 1);

            if (false !== ($colon = strpos($userinfo, ':'))) {
                $user = urldecode(substr($userinfo, 0, $colon));
                $password = urldecode(substr($userinfo, $colon + 1));
            } else {
                $user = urldecode($userinfo);
            }
        }

        if ('' !== $rest && '/' !== $rest[0]) {
            $slash = strpos($rest, '/');
            $authority = false === $slash ? $rest : substr($rest, 0, $slash);
            $rest = false === $slash ? '' : substr($rest, $slash);

            $hp = explode(':', $authority, 2);
            $host = $hp[0] ?? '';
            if (isset($hp[1]) && '' !== $hp[1] && ctype_digit($hp[1])) {
                $port = (int) $hp[1];
            }
        }

        $query = [];
        if ('' !== $queryStr) {
            parse_str($queryStr, $query);
        }

        return new self(
            scheme: $scheme,
            host: $host,
            port: $port,
            user: $user,
            password: $password,
            query: $query
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** @return array<string,mixed> */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getProvider(): string
    {
        $scheme = strtolower($this->scheme);
        if (str_starts_with($scheme, 'ai+')) {
            return substr($scheme, 3);
        }

        return $scheme;
    }
}
