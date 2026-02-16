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
use Symfony\AI\Store\Query\VectorQuery;
use Symfony\AI\Store\StoreInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class EmbeddingProvider implements MemoryProviderInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly Model $model,
        private readonly StoreInterface $vectorStore,
    ) {
    }

    public function load(Input $input): array
    {
        $messages = $input->getMessageBag()->getMessages();
        /** @var MessageInterface|null $userMessage */
        $userMessage = array_last($messages) ?? null;

        if (!$userMessage instanceof UserMessage) {
            return [];
        }

        $userMessageTextContent = array_filter(
            $userMessage->getContent(),
            static fn (ContentInterface $content): bool => $content instanceof Text,
        );

        if (0 === \count($userMessageTextContent)) {
            return [];
        }

        $userMessageTextContent = array_shift($userMessageTextContent);

        $vectors = $this->platform->invoke($this->model->getName(), $userMessageTextContent->getText())->asVectors();
        $foundEmbeddingContent = $this->vectorStore->query(new VectorQuery($vectors[0]));

        $content = '';
        foreach ($foundEmbeddingContent as $document) {
            $content .= json_encode($document->getMetadata());
        }

        if ('' === $content) {
            return [];
        }

        return [new Memory('## Dynamic memories fitting user message'.\PHP_EOL.\PHP_EOL.$content)];
    }
}
