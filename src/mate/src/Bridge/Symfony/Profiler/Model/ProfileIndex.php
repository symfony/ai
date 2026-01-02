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
        public readonly string $token,
        public readonly string $ip,
        public readonly string $method,
        public readonly string $url,
        public readonly int $time,
        public readonly ?int $statusCode = null,
        public readonly ?string $parentToken = null,
        public readonly ?string $context = null,
        public readonly ?string $type = null,
    ) {
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
