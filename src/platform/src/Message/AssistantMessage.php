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
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class AssistantMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    /**
     * @var ContentInterface[]
     */
    private readonly array $content;

    public function __construct(ContentInterface ...$content)
    {
        $this->content = $content;
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::Assistant;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function hasToolCalls(): bool
    {
        foreach ($this->content as $part) {
            if ($part instanceof ToolCall) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ToolCall[]
     */
    public function getToolCalls(): array
    {
        return array_values(array_filter(
            $this->content,
            static fn (ContentInterface $part) => $part instanceof ToolCall,
        ));
    }

    public function hasThinking(): bool
    {
        foreach ($this->content as $part) {
            if ($part instanceof Thinking) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Thinking[]
     */
    public function getThinking(): array
    {
        return array_values(array_filter(
            $this->content,
            static fn (ContentInterface $part) => $part instanceof Thinking,
        ));
    }

    public function asText(): ?string
    {
        $textParts = [];
        foreach ($this->content as $part) {
            if ($part instanceof Text) {
                $textParts[] = $part->getText();
            }
        }

        if ([] === $textParts) {
            return null;
        }

        return implode('', $textParts);
    }
}
