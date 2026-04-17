<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Profiler\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 *
 * @phpstan-type ProfileIndexData array{
 *     token: string,
 *     ip: string,
 *     method: string,
 *     url: string,
 *     time: int,
 *     time_formatted: non-falsy-string,
 *     status_code: int|null,
 *     parent_token: string|null,
 *     context?: string,
 *     resource_uri: string,
 * }
 *
 * @internal
 */
class ProfileIndex
{
    public function __construct(
        private readonly string $token,
        private readonly string $ip,
        private readonly string $method,
        private readonly string $url,
        private readonly int $time,
        private readonly ?int $statusCode = null,
        private readonly ?string $parentToken = null,
        private readonly ?string $context = null,
        private readonly ?string $type = null,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getParentToken(): ?string
    {
        return $this->parentToken;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return ProfileIndexData
     */
    public function toArray(): array
    {
        $data = [
            'token' => $this->token,
            'ip' => $this->ip,
            'method' => $this->method,
            'url' => $this->url,
            'time' => $this->time,
            'time_formatted' => date(\DateTimeInterface::ATOM, $this->time),
            'status_code' => $this->statusCode,
            'parent_token' => $this->parentToken,
            'resource_uri' => \sprintf('symfony-profiler://profile/%s', $this->token),
        ];

        if (null !== $this->context) {
            $data['context'] = $this->context;
        }

        return $data;
    }
}
