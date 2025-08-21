<?php

namespace Symfony\AI\Platform\Bridge\Ollama;

class OllamaMessageChunk
{
    /**
     * @param array<string, mixed> $message
     */
    public function __construct(
        public readonly string $model,
        public readonly string $created_at,
        public readonly array $message,
        public readonly bool $done
    ) {}

    public static function fromJsonString(string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['model'] ?? '',
            $data['created_at'] ?? '',
            $data['message'] ?? [],
            $data['done'] ?? false
        );
    }

    public function __toString(): string
    {
        // Return the assistant's message content if available
        return $this->message['content'] ?? '';
    }

    public function getRole(): ?string
    {
        return $this->message['role'] ?? null;
    }

    public function getContent(): ?string
    {
        return $this->message['content'] ?? null;
    }
}
