<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class RealtimeSessionResult extends BaseResult
{
    /**
     * @param list<string>         $modalities
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private readonly string $id,
        private readonly string $clientSecret,
        private readonly int $expiresAt,
        private readonly string $model,
        private readonly ?string $voice = null,
        private readonly array $modalities = ['text', 'audio'],
        private readonly array $raw = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getVoice(): ?string
    {
        return $this->voice;
    }

    /**
     * @return list<string>
     */
    public function getModalities(): array
    {
        return $this->modalities;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return [
            'id' => $this->id,
            'client_secret' => $this->clientSecret,
            'expires_at' => $this->expiresAt,
            'model' => $this->model,
            'voice' => $this->voice,
            'modalities' => $this->modalities,
        ];
    }
}
