<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ToolCallMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    /**
     * @var ContentInterface[]
     */
    private readonly array $content;

    private bool $isError = false;

    public function __construct(
        private readonly ToolCall $toolCall,
        ContentInterface ...$content,
    ) {
        $this->id = Uuid::v7();
        $this->content = $content;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function withIsError(bool $isError): self
    {
        $clone = clone $this;
        $clone->isError = $isError;

        return $clone;
    }

    public function getRole(): Role
    {
        return Role::ToolCall;
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function asText(): ?string
    {
        $textParts = [];
        foreach ($this->content as $content) {
            if ($content instanceof Text) {
                $textParts[] = $content->getText();
            }
        }

        if ([] === $textParts) {
            return null;
        }

        return implode(' ', $textParts);
    }
}
