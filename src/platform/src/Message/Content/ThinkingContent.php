<?php

namespace Symfony\AI\Platform\Message\Content;

final class ThinkingContent implements ContentInterface
{
    public function __construct(
        private readonly string $content,
        private readonly ?string $signature = null,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
}
