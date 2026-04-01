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

use Symfony\AI\Platform\Message\Content\Collection;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\ThinkingContent;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 *
 * @extends \IteratorAggregate<int, ContentInterface>
 */
final class AssistantMessage implements MessageInterface, \IteratorAggregate
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    private readonly ?ContentInterface $content;

    public function __construct(
        ContentInterface ...$content,
    ) {
        $this->content = match (true) {
            0 === \count($content) => null,
            1 === \count($content) => $content[0],
            default => new Collection(...$content),
        };

        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::Assistant;
    }

    public function hasToolCalls(): bool
    {
        foreach ($this as $content) {
            if ($content instanceof ToolCall) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return non-empty-list<ToolCall>|null
     */
    public function getToolCalls(): ?array
    {
        $toolCalls = null;

        foreach ($this as $content) {
            if ($content instanceof ToolCall) {
                $toolCalls[] = $content;
            }
        }

        return $toolCalls;
    }

    public function getContent(): ?string
    {
        foreach ($this as $content) {
            if ($content instanceof Text) {
                return $content->getText();
            }
        }

        return null;
    }

    public function getIterator(): \Traversable
    {
        if (is_iterable($this->content)) {
            yield from $this->content;
        }

        yield $this->content;
    }

    public function hasThinkingContent(): bool
    {
        foreach ($this as $content) {
            if ($content instanceof ThinkingContent) {
                return true;
            }
        }

        return false;
    }

    public function getThinkingContent(): ?string
    {
        foreach ($this as $content) {
            if ($content instanceof ThinkingContent) {
                return $content->getContent();
            }
        }

        return null;
    }

    public function getThinkingSignature(): ?string
    {
        foreach ($this as $content) {
            if ($content instanceof ThinkingContent) {
                return $content->getSignature();
            }
        }

        return null;
    }
}
