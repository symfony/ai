<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Memory;

use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Store\VectorStoreInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final readonly class EmbeddingProvider implements MemoryProviderInterface
{
    public function __construct(
        private PlatformInterface $platform,
        private Model $model,
        private VectorStoreInterface $vectorStore,
    ) {
    }

    public function loadMemory(Input $input): array
    {
        $messages = $input->messages->getMessages();
        /** @var MessageInterface|null $userMessage */
        $userMessage = $messages[array_key_last($messages)] ?? null;

        if (!$userMessage instanceof UserMessage) {
            return [];
        }

        $userMessageTextContent = array_filter(
            $userMessage->content,
            static fn (ContentInterface $content): bool => $content instanceof Text,
        );

        if (0 === \count($userMessageTextContent)) {
            return [];
        }

        $userMessageTextContent = array_shift($userMessageTextContent);

        $vectors = $this->platform->invoke($this->model, $userMessageTextContent->text)->asVectors();
        $foundEmbeddingContent = $this->vectorStore->query($vectors[0]);
        if (0 === \count($foundEmbeddingContent)) {
            return [];
        }

        $content = '## Dynamic memories fitting user message'.\PHP_EOL.\PHP_EOL;
        foreach ($foundEmbeddingContent as $document) {
            $content .= json_encode($document->metadata);
        }

        return [new Memory($content)];
    }
}
